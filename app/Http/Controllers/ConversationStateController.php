<?php

namespace App\Http\Controllers;

use App\Enums\ConversationStatus;
use App\Http\Requests\ManageConversationRequest;
use App\Models\Conversation;
use App\Services\ManageConversation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ConversationStateController extends Controller
{
    public function resolve(ManageConversationRequest $request, Conversation $subscriberConversation, ManageConversation $manager): RedirectResponse
    {
        $manager->resolve($subscriberConversation, $request->user());

        return back()->with('toast', ['type' => 'success', 'title' => __('Conversation resolved'), 'message' => __('The conversation is now closed.')]);
    }

    public function reopen(ManageConversationRequest $request, Conversation $subscriberConversation, ManageConversation $manager): RedirectResponse
    {
        $manager->reopen($subscriberConversation, $request->user());

        return back()->with('toast', ['type' => 'success', 'title' => __('Conversation reopened'), 'message' => __('The conversation can receive replies again.')]);
    }

    public function archive(ManageConversationRequest $request, Conversation $subscriberConversation, ManageConversation $manager): RedirectResponse
    {
        $manager->archive($subscriberConversation, $request->user());

        return redirect()->route('conversations.index')->with('toast', ['type' => 'success', 'title' => __('Conversation archived'), 'message' => __('The conversation was moved to the archive.')]);
    }

    public function destroy(ManageConversationRequest $request, Conversation $subscriberConversation, ManageConversation $manager): RedirectResponse
    {
        Gate::authorize('delete', $subscriberConversation);
        $manager->delete($subscriberConversation, $request->user());

        return redirect()->route('conversations.index')->with('toast', ['type' => 'success', 'title' => __('Conversation deleted'), 'message' => __('The conversation was safely removed from the inbox.')]);
    }

    public function read(ManageConversationRequest $request, Conversation $subscriberConversation, ManageConversation $manager): JsonResponse
    {
        $conversation = $manager->markRead($subscriberConversation, $request->user());

        return response()->json(['data' => ['unread_count' => $conversation->unread_count]]);
    }

    public function activity(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Conversation::class);
        $base = Conversation::query()->ownedBy($request->user());
        $latest = (clone $base)->max('last_message_at');

        return response()->json(['data' => [
            'latest_activity' => $latest ? CarbonImmutable::parse($latest)->toIso8601String() : null,
            'unread_count' => (clone $base)->whereNotIn('status', [ConversationStatus::ARCHIVED, ConversationStatus::SPAM])->sum('unread_count'),
            'needs_human_count' => (clone $base)->where('status', ConversationStatus::NEEDS_HUMAN)->count(),
        ]]);
    }
}
