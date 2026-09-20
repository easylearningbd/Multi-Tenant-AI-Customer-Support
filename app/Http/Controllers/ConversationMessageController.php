<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationReplyRequest;
use App\Http\Resources\ConversationMessageResource;
use App\Models\Conversation;
use App\Services\SendAgentConversationReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ConversationMessageController extends Controller
{
    public function index(Request $request, Conversation $subscriberConversation): JsonResponse
    {
        Gate::authorize('view', $subscriberConversation);
        $request->validate([
            'after' => ['nullable', 'uuid'],
            'before' => ['nullable', 'uuid'],
        ]);
        $limit = max(10, (int) config('neuraldesk.conversations.message_page_size', 50));
        $query = $subscriberConversation->messages()->reorder()->with([
            'sender:id,name', 'replyTo:id,uuid',
            'attachments:id,uuid,conversation_message_id,original_name,mime_type,size',
        ]);
        $before = $request->string('before')->toString();
        $after = $request->string('after')->toString();

        if ($before !== '') {
            $anchor = $subscriberConversation->messages()->reorder()->where('uuid', $before)->firstOrFail();
            $messages = $query->where('id', '<', $anchor->id)->latest('id')->limit($limit)->get()->reverse()->values();
            $hasMore = ($oldest = $messages->first())
                ? $subscriberConversation->messages()->reorder()->where('id', '<', $oldest->id)->exists()
                : false;
        } elseif ($after !== '') {
            $anchor = $subscriberConversation->messages()->reorder()->where('uuid', $after)->firstOrFail();
            $messages = $query->where('id', '>', $anchor->id)->oldest('id')->limit($limit)->get();
            $hasMore = false;
        } else {
            $messages = $query->latest('id')->limit($limit)->get()->reverse()->values();
            $hasMore = ($oldest = $messages->first())
                ? $subscriberConversation->messages()->reorder()->where('id', '<', $oldest->id)->exists()
                : false;
        }

        return response()->json(['data' => [
            'conversation' => $this->conversationState($subscriberConversation->fresh()),
            'messages' => ConversationMessageResource::collection($messages)->resolve($request),
            'has_more' => $hasMore,
        ]]);
    }

    public function store(
        StoreConversationReplyRequest $request,
        Conversation $subscriberConversation,
        SendAgentConversationReply $replies,
    ): JsonResponse|RedirectResponse {
        try {
            $message = $replies->handle(
                $subscriberConversation,
                $request->user(),
                $request->validated('message'),
                (string) $request->validated('idempotency_key'),
                $request->file('attachments', []),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => __('The reply could not be sent. Please try again.')], 500);
            }

            return back()->with('toast', [
                'type' => 'error', 'title' => __('Reply failed'),
                'message' => __('The reply could not be sent. Please try again.'),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'message' => (new ConversationMessageResource($message))->resolve($request),
                'conversation' => $this->conversationState($subscriberConversation->fresh()),
            ]], 201);
        }

        return back()->with('toast', [
            'type' => 'success', 'title' => __('Reply sent'),
            'message' => __('Your reply was delivered to the visitor.'),
        ]);
    }

    /** @return array<string, mixed> */
    private function conversationState(Conversation $conversation): array
    {
        return [
            'uuid' => $conversation->uuid,
            'status' => $conversation->status->value,
            'handling_mode' => $conversation->effectiveHandlingMode()->value,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }
}
