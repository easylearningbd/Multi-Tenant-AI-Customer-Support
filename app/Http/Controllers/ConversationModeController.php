<?php

namespace App\Http\Controllers;

use App\Enums\ConversationHandlingMode;
use App\Http\Requests\UpdateConversationModeRequest;
use App\Models\Conversation;
use App\Services\ManageConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class ConversationModeController extends Controller
{
    public function __invoke(UpdateConversationModeRequest $request, Conversation $subscriberConversation, ManageConversation $manager): JsonResponse|RedirectResponse
    {
        $conversation = $manager->changeMode(
            $subscriberConversation,
            $request->user(),
            ConversationHandlingMode::from((string) $request->validated('mode')),
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'status' => $conversation->status->value,
                'handling_mode' => $conversation->effectiveHandlingMode()->value,
            ]]);
        }

        return back()->with('toast', [
            'type' => 'success', 'title' => __('Conversation updated'),
            'message' => $conversation->acceptsAiReplies()
                ? __('Automatic replies are active for future visitor messages.')
                : __('Manual replies are now active.'),
        ]);
    }
}
