<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class ConversationHandoffRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $conversationId)
    {
        $this->onQueue((string) config('neuraldesk.queues.notifications', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $conversation = Conversation::query()->whereKey($this->conversationId)
            ->where('user_id', $notifiable->id)->first();

        return [
            'type' => 'conversation_handoff_requested',
            'conversation_uuid' => $conversation?->uuid,
            'message' => __('A visitor is waiting for a human reply.'),
        ];
    }
}
