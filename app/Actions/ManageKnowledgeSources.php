<?php

namespace App\Actions;

use App\Contracts\VectorStoreInterface;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Jobs\ProcessKnowledgeSource;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Models\User;
use App\Services\CurrentSubscriptionResolver;
use App\Services\PlanLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ManageKnowledgeSources
{
    public function __construct(
        private readonly CurrentSubscriptionResolver $subscriptions,
        private readonly PlanLimitService $limits,
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
        $created = [];
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $type = match ($extension) {
                'txt' => KnowledgeSourceType::TXT,
                'md' => KnowledgeSourceType::MARKDOWN,
                'csv' => KnowledgeSourceType::CSV,
                'pdf' => KnowledgeSourceType::PDF,
                'docx' => KnowledgeSourceType::DOCX,
                default => throw ValidationException::withMessages(['files' => __('One or more files use an unsupported extension.')]),
            };
            $path = 'user-'.$user->id.'/bot-'.$bot->id.'/'.Str::uuid().'.'.$extension;
            $disk = Storage::disk(config('neuraldesk.storage.knowledge_disk'));
            $stored = $disk->putFileAs(dirname($path), $file, basename($path));
            if (! is_string($stored)) {
                throw ValidationException::withMessages(['files' => __('The source file could not be stored safely.')]);
            }
            try {
                $created[] = $this->create($user, $bot, [
                    'type' => $type,
                    'name' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 180, ''),
                    'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'file_path' => $stored,
                    'file_size_bytes' => max(0, (int) $file->getSize()),
                ]);
            } catch (Throwable $exception) {
                $disk->delete($stored);
                throw $exception;
            }
        }

        return $created;
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
        $token = (string) Str::uuid();
        $source->forceFill([
            'status' => KnowledgeSourceStatus::QUEUED,
            'failure_message' => null,
            'processing_token' => $token,
        ])->save();
        ProcessKnowledgeSource::dispatch($source->id, $user->id, $bot->id, $token)->afterCommit();

        return $source;
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
            $subscription = $this->subscriptions->for($owner);
            if (! $subscription || ! $subscription->grantsEntitlements()) {
                throw ValidationException::withMessages(['plan_limit' => __('An active subscription is required before training knowledge.')]);
            }
            $this->limits->ensureAllows($subscription, 'knowledge_sources_limit', $owner->knowledgeSources()->count());
            $requestedBytes = (int) ($attributes['file_size_bytes'] ?? 0);
            if ($requestedBytes > 0) {
                $usedMb = (int) ceil($owner->knowledgeSources()->sum('file_size_bytes') / 1048576);
                $this->limits->ensureAllows($subscription, 'storage_mb_limit', $usedMb, max(1, (int) ceil($requestedBytes / 1048576)));
            }

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
        }, 3);
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
