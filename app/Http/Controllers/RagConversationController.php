<?php

namespace App\Http\Controllers;

use App\Actions\QueueConversationMessage;
use App\Http\Requests\QueueRagMessageRequest;
use App\Http\Resources\ConversationMessageResource;
use App\Models\Bot;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class RagConversationController extends Controller
{
    public function store(QueueRagMessageRequest $request, Bot $subscriberBot, QueueConversationMessage $messages): JsonResponse
    {
        $queued = $messages->execute(
            $request->user(),
            $subscriberBot,
            (string) $request->validated('message'),
            (string) $request->validated('idempotency_key'),
            $request->validated('conversation_uuid'),
        );

        return response()->json([
            'data' => [
                'conversation_uuid' => $queued->conversation->uuid,
                'message_uuid' => $queued->message->uuid,
                'status' => $queued->message->status->value,
                'poll_url' => route('bots.rag.conversations.show', [$subscriberBot, $queued->conversation]),
            ],
        ], $queued->created ? 202 : 200);
    }

    public function show(Request $request, Bot $subscriberBot, Conversation $subscriberConversation): JsonResponse
    {
        abort_unless($subscriberConversation->user_id === $request->user()->id && $subscriberConversation->bot_id === $subscriberBot->id, 404);
        Gate::authorize('view', $subscriberConversation);

        $messages = $subscriberConversation->messages()
            ->with(['replyTo:id,uuid', 'citations:id,conversation_message_id,source_uuid,source_name,rank'])
            ->paginate(50);

        return response()->json([
            'data' => [
                'uuid' => $subscriberConversation->uuid,
                'status' => $subscriberConversation->status->value,
                'subject' => $subscriberConversation->subject,
                'messages' => ConversationMessageResource::collection($messages->items())->resolve($request),
            ],
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }
}
