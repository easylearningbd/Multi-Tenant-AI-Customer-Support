<?php

namespace App\Http\Controllers;

use App\Actions\CreateVisitorSession;
use App\Actions\QueuePublicWidgetMessage;
use App\Actions\RequestPublicHandoff;
use App\Actions\SaveVisitorPrechat;
use App\Http\Requests\BootstrapPublicWidgetRequest;
use App\Http\Requests\PublicWidgetHandoffRequest;
use App\Http\Requests\PublicWidgetMessageRequest;
use App\Http\Requests\PublicWidgetPrechatRequest;
use App\Http\Resources\ConversationMessageResource;
use App\Models\Conversation;
use App\Services\PublicWidgetContext;
use App\Services\PublicWidgetPayload;
use App\Services\PublicWidgetSessionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicWidgetApiController extends Controller
{
    public function bootstrap(BootstrapPublicWidgetRequest $request, string $publicWidget, PublicWidgetContext $context, CreateVisitorSession $sessions, PublicWidgetPayload $payload): JsonResponse
    {
        $widget = $context->resolve($publicWidget);
        $created = $sessions->handle($widget, (string) $request->validated('access_proof'));

        return response()->json(['data' => $payload->make($widget, $created)], 201);
    }

    public function prechat(PublicWidgetPrechatRequest $request, string $publicWidget, PublicWidgetContext $context, PublicWidgetSessionResolver $sessions, SaveVisitorPrechat $prechat): JsonResponse
    {
        $widget = $context->resolve($publicWidget);
        $session = $sessions->resolve($request, $widget);
        $prechat->handle($session, $request->validated('fields'));

        return response()->json(['data' => ['completed' => true]]);
    }

    public function message(PublicWidgetMessageRequest $request, string $publicWidget, PublicWidgetContext $context, PublicWidgetSessionResolver $sessions, QueuePublicWidgetMessage $messages): JsonResponse
    {
        $widget = $context->resolve($publicWidget);
        $session = $sessions->resolve($request, $widget);
        $queued = $messages->handle(
            $session,
            (string) $request->validated('message'),
            (string) $request->validated('idempotency_key'),
            $request->validated('conversation_uuid'),
        );

        return response()->json(['data' => [
            'conversation_uuid' => $queued->conversation->uuid,
            'message_uuid' => $queued->message->uuid,
            'status' => $queued->message->status->value,
        ]], $queued->created ? 202 : 200);
    }

    public function conversation(Request $request, string $publicWidget, string $conversationUuid, PublicWidgetContext $context, PublicWidgetSessionResolver $sessions): JsonResponse
    {
        $widget = $context->resolve($publicWidget);
        $session = $sessions->resolve($request, $widget);
        $conversation = Conversation::query()->where('uuid', $conversationUuid)
            ->where('user_id', $session->user_id)->where('bot_id', $session->bot_id)
            ->where('visitor_session_id', $session->id)->firstOrFail();
        $messages = $conversation->messages()->with(['replyTo:id,uuid', 'citations:id,conversation_message_id,source_uuid,source_name,rank'])
            ->limit(100)->get();

        return response()->json(['data' => [
            'uuid' => $conversation->uuid,
            'status' => $conversation->status->value,
            'messages' => ConversationMessageResource::collection($messages)->resolve($request),
        ]]);
    }

    public function handoff(PublicWidgetHandoffRequest $request, string $publicWidget, PublicWidgetContext $context, PublicWidgetSessionResolver $sessions, RequestPublicHandoff $handoff): JsonResponse
    {
        $widget = $context->resolve($publicWidget);
        $session = $sessions->resolve($request, $widget);
        $conversation = $handoff->handle($session, (string) $request->validated('conversation_uuid'));

        return response()->json(['data' => ['status' => $conversation->status->value]]);
    }
}
