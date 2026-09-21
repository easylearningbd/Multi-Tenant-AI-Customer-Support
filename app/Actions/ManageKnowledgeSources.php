<?php

namespace App\Actions;

use App\Contracts\VectorStoreInterface;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Enums\PlanMetric;
use App\Jobs\ProcessKnowledgeSource;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Services\KnowledgeStorageUsageService;
use App\Services\PlanUsageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ManageKnowledgeSources
{
    public function __construct(
        private readonly PlanUsageService $usage,
        private readonly KnowledgeStorageUsageService $storageUsage,
        private readonly VectorStoreInterface $vectors,
    ) {}

    public function createText(User $user, Bot $bot, string $text, ?string $name = null): KnowledgeSource
    {
        return $this->create($user, $bot, [
            'type' => KnowledgeSourceType::TEXT,
            'name' => Str::limit(Str::squish($name ?: Str::before($text, "\n")), 180, ''),
            'raw_text' => $text,
        ]);
    }

    /** @param list<UploadedFile> $files @return list<KnowledgeSource> */
    public function createFiles(User $user, Bot $bot, array $files): array
    {
        $this->assertOwnership($user, $bot);
        $descriptors = collect($files)->map(function (UploadedFile $file) use ($user, $bot): array {
            $extension = strtolower($file->getClientOriginalExtension());
            $type = match ($extension) {
                'txt' => KnowledgeSourceType::TXT,
                'md' => KnowledgeSourceType::MARKDOWN,
                'csv' => KnowledgeSourceType::CSV,
                'pdf' => KnowledgeSourceType::PDF,
                'docx' => KnowledgeSourceType::DOCX,
                default => throw ValidationException::withMessages(['files' => __('One or more files use an unsupported extension.')]),
            };

            return [
                'file' => $file,
                'path' => 'user-'.$user->id.'/bot-'.$bot->id.'/'.Str::uuid().'.'.$extension,
                'attributes' => [
                    'type' => $type,
                    'name' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 180, ''),
                    'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'file_size_bytes' => max(0, (int) $file->getSize()),
                ],
            ];
        })->values();
        $totalBytes = (int) $descriptors->sum(fn (array $descriptor): int => $descriptor['attributes']['file_size_bytes']);
        $reservation = $this->storageUsage->reserve($user, $bot, $totalBytes, $descriptors->count());
        $disk = Storage::disk(config('neuraldesk.storage.knowledge_disk'));
        $storedPaths = [];

        try {
            $descriptors = $descriptors->map(function (array $descriptor) use ($disk, &$storedPaths): array {
                $stored = $disk->putFileAs(dirname($descriptor['path']), $descriptor['file'], basename($descriptor['path']));
                if (! is_string($stored)) {
                    throw ValidationException::withMessages(['files' => __('The source file could not be stored safely.')]);
                }
                $storedPaths[] = $stored;
                $descriptor['attributes']['file_path'] = $stored;

                return $descriptor;
            });

            return DB::transaction(function () use ($user, $bot, $descriptors, $reservation): array {
                $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($user->id);
                $subscription = $this->usage->activeSubscription($owner);
                if ($reservation && $reservation->subscription_id !== $subscription->id) {
                    throw ValidationException::withMessages(['plan_limit' => __('Your subscription changed. Please upload the files again.')]);
                }
                $this->usage->ensureWithinLimit(
                    $subscription,
                    PlanMetric::KNOWLEDGE_SOURCES,
                    $owner->knowledgeSources()->count(),
                    $descriptors->count(),
                );

                $created = $descriptors->map(fn (array $descriptor): KnowledgeSource => $this->newSource($owner, $bot, $descriptor['attributes']))->all();
                if ($reservation) {
                    $this->storageUsage->commit($reservation, $created[0] ?? null);
                }

                return $created;
            }, 3);
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $disk->delete($path);
            }
            if ($reservation) {
                $this->storageUsage->release($reservation);
            }
            throw $exception;
        }
    }

    public function createWebsite(User $user, Bot $bot, string $url, bool $sitemap, int $pageLimit): KnowledgeSource
    {
        return $this->create($user, $bot, [
            'type' => $sitemap ? KnowledgeSourceType::SITEMAP : KnowledgeSourceType::WEBSITE,
            'name' => Str::limit((string) parse_url($url, PHP_URL_HOST), 180, ''),
            $sitemap ? 'sitemap_url' : 'source_url' => $url,
            'page_limit' => $pageLimit,
        ]);
    }

    public function retry(User $user, Bot $bot, KnowledgeSource $source): KnowledgeSource
    {
        $this->assertSource($user, $bot, $source);

        return DB::transaction(function () use ($user, $bot, $source): KnowledgeSource {
            $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($user->id);
            $this->usage->activeSubscription($owner);
            $locked = KnowledgeSource::query()->ownedBy($owner)->forBot($bot)->lockForUpdate()->findOrFail($source->id);
            $token = (string) Str::uuid();
            $locked->forceFill([
                'status' => KnowledgeSourceStatus::QUEUED,
                'failure_message' => null,
                'processing_token' => $token,
            ])->save();
            ProcessKnowledgeSource::dispatch($locked->id, $owner->id, $bot->id, $token)->afterCommit();

            return $locked;
        }, 3);
    }

    public function retryAll(User $user, Bot $bot): int
    {
        $this->assertOwnership($user, $bot);
        $count = 0;
        KnowledgeSource::query()->ownedBy($user)->forBot($bot)->select('id')->chunkById(100, function ($sources) use ($user, $bot, &$count): void {
            foreach ($sources as $source) {
                $full = KnowledgeSource::query()->ownedBy($user)->forBot($bot)->findOrFail($source->id);
                $this->retry($user, $bot, $full);
                $count++;
            }
        });

        return $count;
    }

    public function delete(User $user, Bot $bot, KnowledgeSource $source): void
    {
        $this->assertSource($user, $bot, $source);
        $path = $source->file_path;
        DB::transaction(function () use ($user, $bot, $source): void {
            $locked = KnowledgeSource::query()->ownedBy($user)->forBot($bot)->lockForUpdate()->findOrFail($source->id);
            $this->vectors->deleteBySource($user->id, $bot->id, $locked->id);
            $locked->forceFill(['processing_token' => null])->save();
            $locked->delete();
        }, 3);
        if ($path) {
            Storage::disk(config('neuraldesk.storage.knowledge_disk'))->delete($path);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function create(User $user, Bot $bot, array $attributes): KnowledgeSource
    {
        $this->assertOwnership($user, $bot);

        return DB::transaction(function () use ($user, $bot, $attributes): KnowledgeSource {
            $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($user->id);
            $subscription = $this->usage->activeSubscription($owner);
            $this->usage->ensureWithinLimit(
                $subscription,
                PlanMetric::KNOWLEDGE_SOURCES,
                $owner->knowledgeSources()->count(),
            );

            return $this->newSource($owner, $bot, $attributes);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    private function newSource(User $owner, Bot $bot, array $attributes): KnowledgeSource
    {
        $source = new KnowledgeSource;
        $source->uuid = (string) Str::uuid();
        $source->user_id = $owner->id;
        $source->bot_id = $bot->id;
        $source->created_by = $owner->id;
        $source->fill($attributes);
        $source->status = KnowledgeSourceStatus::QUEUED;
        $source->processing_token = (string) Str::uuid();
        $source->save();
        ProcessKnowledgeSource::dispatch($source->id, $owner->id, $bot->id, $source->processing_token)->afterCommit();

        return $source;
    }

    private function assertOwnership(User $user, Bot $bot): void
    {
        abort_unless($bot->user_id === $user->id, 404);
    }

    private function assertSource(User $user, Bot $bot, KnowledgeSource $source): void
    {
        abort_unless($bot->user_id === $user->id && $source->user_id === $user->id && $source->bot_id === $bot->id, 404);
    }
}
