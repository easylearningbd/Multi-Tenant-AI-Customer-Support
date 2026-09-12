<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class UpdateAdminProfile
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    /**
     * @param  array{name: string, email: string, phone: ?string}  $attributes
     */
    public function handle(User $admin, array $attributes, ?UploadedFile $avatar): void
    {
        $newAvatarPath = $avatar ? $this->storeAvatar($avatar) : null;
        $oldAvatarPath = $admin->avatar_path;

        try {
            DB::transaction(function () use ($admin, $attributes, $newAvatarPath): void {
                $admin->name = $attributes['name'];
                $admin->email = $attributes['email'];
                $admin->phone = $attributes['phone'];

                if ($admin->isDirty('email')) {
                    $admin->email_verified_at = null;
                }

                if ($newAvatarPath !== null) {
                    $admin->avatar_path = $newAvatarPath;
                }

                $admin->save();
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
