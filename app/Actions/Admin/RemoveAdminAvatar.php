<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;

final class RemoveAdminAvatar
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    public function handle(User $admin): bool
    {
        $oldAvatarPath = $admin->avatar_path;

        if ($oldAvatarPath === null) {
            return false;
        }

        DB::transaction(function () use ($admin): void {
            $admin->avatar_path = null;
            $admin->save();
        });

        if (User::isManagedAvatarPath($oldAvatarPath)) {
            $this->filesystems->disk(config('admin.profile.avatar_disk'))->delete($oldAvatarPath);
        }

        return true;
    }
}
