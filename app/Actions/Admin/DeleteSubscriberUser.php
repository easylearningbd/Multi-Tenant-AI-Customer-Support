<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DeleteSubscriberUser
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    public function handle(User $subscriber): void
    {
        if ($subscriber->role !== UserRole::USER) {
            throw new LogicException('Only subscriber accounts may be deleted by this action.');
        }

        $avatarPath = $subscriber->avatar_path;

        DB::transaction(function () use ($subscriber): void {
            DB::table('sessions')->where('user_id', $subscriber->id)->delete();
            DB::table('password_reset_tokens')->where('email', $subscriber->email)->delete();
            $subscriber->delete();
        });

        if (User::isManagedAvatarPath($avatarPath)) {
            $this->filesystems->disk(config('admin.profile.avatar_disk'))->delete($avatarPath);
        }
    }
}
