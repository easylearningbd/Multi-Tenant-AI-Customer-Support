<?php

namespace App\Actions;

use App\Models\Bot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteBot
{
    public function handle(User $owner, Bot $bot): void
    {
        DB::transaction(function () use ($owner, $bot): void {
            $lockedBot = Bot::query()
                ->ownedBy($owner)
                ->lockForUpdate()
                ->findOrFail($bot->id);

            $lockedBot->forceFill(['is_active' => false])->save();
            $lockedBot->delete();
        }, 3);
    }
}
