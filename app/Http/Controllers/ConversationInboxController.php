<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListConversationsRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationInboxQuery;
use App\Services\CurrentSubscriptionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ConversationInboxController extends Controller
{
    public function __invoke(
        ListConversationsRequest $request,
        ConversationInboxQuery $inbox,
        CurrentSubscriptionResolver $subscriptions,
    ): View {
        Gate::authorize('viewAny', Conversation::class);
        /** @var User $subscriber */
        $subscriber = $request->user();
        $filters = $request->validated();
        $filter = (string) ($filters['filter'] ?? 'all');
        $search = $filters['q'] ?? null;
        $result = $inbox->for($subscriber, $filter, $search);
        $selectedUuid = $filters['conversation'] ?? null;
        $selected = null;

        if (is_string($selectedUuid)) {
            $selected = Conversation::query()->ownedBy($subscriber)->where('uuid', $selectedUuid)
                ->with(['bot:id,name,display_name', 'visitorSession:id,prechat_data', 'assignedAgent:id,name'])
                ->firstOrFail();
            Gate::authorize('view', $selected);
        } elseif ($result['conversations']->isNotEmpty()) {
            $selected = $result['conversations']->first();
        }

        $messages = collect();
        $hasOlderMessages = false;
        if ($selected) {
            $limit = max(10, (int) config('neuraldesk.conversations.message_page_size', 50));
            $messages = $selected->messages()->reorder()->with([
                'sender:id,name',
                'replyTo:id,uuid',
                'attachments:id,uuid,conversation_message_id,original_name,mime_type,size',
            ])->latest('id')->limit($limit)->get()->reverse()->values();
            $oldestId = $messages->first()?->id;
            $hasOlderMessages = $oldestId !== null && $selected->messages()->reorder()->where('id', '<', $oldestId)->exists();
        }

        return view('subscriber.conversations', [
            ...$result,
            'selectedConversation' => $selected,
            'messages' => $messages,
            'hasOlderMessages' => $hasOlderMessages,
            'filter' => $filter,
            'search' => $search,
            'explicitSelection' => is_string($selectedUuid),
            'subscriberPlanName' => $subscriptions->for($subscriber)?->planName() ?? __('No active plan'),
        ]);
    }
}
