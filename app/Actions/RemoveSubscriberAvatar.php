<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;

final class RemoveSubscriberAvatar
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    public function handle(User $subscriber): bool
    {
        $oldAvatarPath = $subscriber->avatar_path;

        if ($oldAvatarPath === null) {
            return false;
        }

        DB::transaction(function () use ($subscriber): void {
            $subscriber->avatar_path = null;
            $subscriber->save();
        });

        if (User::isManagedAvatarPath($oldAvatarPath)) {
            $this->filesystems->disk(config('admin.profile.avatar_disk'))->delete($oldAvatarPath);
        }

        return true;
    }
}
