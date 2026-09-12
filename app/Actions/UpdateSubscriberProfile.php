<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class UpdateSubscriberProfile
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    /**
     * @param  array{name: string, email: string, phone: ?string}  $attributes
     */
    public function handle(User $subscriber, array $attributes, ?UploadedFile $avatar): void
    {
        $newAvatarPath = $avatar ? $this->storeAvatar($avatar) : null;
        $oldAvatarPath = $subscriber->avatar_path;

        try {
            DB::transaction(function () use ($subscriber, $attributes, $newAvatarPath): void {
                $subscriber->name = $attributes['name'];
                $subscriber->email = $attributes['email'];
                $subscriber->phone = $attributes['phone'];

                if ($subscriber->isDirty('email')) {
                    $subscriber->email_verified_at = null;
                }

                if ($newAvatarPath !== null) {
                    $subscriber->avatar_path = $newAvatarPath;
                }

                $subscriber->save();
            });
        } catch (Throwable $exception) {
            $this->deleteManagedAvatar($newAvatarPath);

            throw $exception;
        }

        if ($newAvatarPath !== null) {
            $this->deleteManagedAvatar($oldAvatarPath);
        }
    }

    private function storeAvatar(UploadedFile $avatar): string
    {
        $path = $avatar->store(
            config('admin.profile.avatar_directory'),
            config('admin.profile.avatar_disk'),
        );

        if (! is_string($path)) {
            throw new RuntimeException('The profile image could not be stored.');
        }

        return $path;
    }

    private function deleteManagedAvatar(?string $path): void
    {
        if (User::isManagedAvatarPath($path)) {
            $this->filesystems->disk(config('admin.profile.avatar_disk'))->delete($path);
        }
    }
}
