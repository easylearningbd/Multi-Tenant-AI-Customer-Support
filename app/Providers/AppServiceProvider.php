<?php

namespace App\Providers;

use App\Contracts\ChatCompletionProviderInterface;
use App\Contracts\ConversationQueryRewriterInterface;
use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\KnowledgeRetrieverInterface;
use App\Contracts\RagPromptBuilderInterface;
use App\Contracts\VectorStoreInterface;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\KnowledgeSource;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\BotPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\KnowledgeSourcePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PlanPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\UserPolicy;
use App\Services\ConversationQueryRewriter;
use App\Services\KnowledgeRetriever;
use App\Services\MySqlVectorStore;
use App\Services\OpenAIEmbeddingService;
use App\Services\OpenAIResponseService;
use App\Services\RagPromptBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VectorStoreInterface::class, MySqlVectorStore::class);
        $this->app->bind(EmbeddingProviderInterface::class, OpenAIEmbeddingService::class);
        $this->app->bind(KnowledgeRetrieverInterface::class, KnowledgeRetriever::class);
        $this->app->bind(RagPromptBuilderInterface::class, RagPromptBuilder::class);
        $this->app->bind(ChatCompletionProviderInterface::class, OpenAIResponseService::class);
        $this->app->bind(ConversationQueryRewriterInterface::class, ConversationQueryRewriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Bot::class, BotPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(KnowledgeSource::class, KnowledgeSourcePolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('support-ticket-create', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('support-tickets.rate_limits.create_per_minute')),
        )->by('support-ticket-create:'.$request->user()?->id)->response(
            fn (): RedirectResponse => back()->with('toast', [
                'type' => 'warning',
                'title' => __('Too many tickets'),
                'message' => __('Please wait before submitting another support ticket.'),
            ]),
        ));

        RateLimiter::for('support-ticket-reply', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('support-tickets.rate_limits.reply_per_minute')),
        )->by('support-ticket-reply:'.$request->user()?->id)->response(
            fn (): RedirectResponse => back()->with('toast', [
                'type' => 'warning',
                'title' => __('Too many replies'),
                'message' => __('Please wait before sending another reply.'),
            ]),
        ));

        RateLimiter::for('bank-transfer-payment', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('billing.bank_transfer.submission_rate_per_minute')),
        )->by('bank-transfer-payment:'.$request->user()?->id)->response(
            fn (): RedirectResponse => back()->with('toast', [
                'type' => 'warning',
                'title' => __('Too many payment attempts'),
                'message' => __('Please wait before submitting another bank-transfer payment.'),
            ]),
        ));

        RateLimiter::for('knowledge-training', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('knowledge-training:'.$request->user()?->id));

        RateLimiter::for('rag-message', function (Request $request): Limit {
            $routeBot = $request->route('subscriberBot');
            $botKey = $routeBot instanceof Bot ? $routeBot->id : (string) $routeBot;

            return Limit::perMinute(max(1, (int) config('neuraldesk.rag.requests_per_minute', 12)))
                ->by('rag-message:'.$request->user()?->id.':'.$botKey);
        });

        RateLimiter::for('widget-bootstrap', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('neuraldesk.widgets.bootstrap_rate_per_minute', 30)),
        )->by('widget-bootstrap:'.$request->route('publicWidget').':'.$request->ip()));

        RateLimiter::for('widget-message', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('neuraldesk.widgets.message_rate_per_minute', 12)),
        )->by('widget-message:'.$request->route('publicWidget').':'.$request->ip().':'.hash('sha256', (string) $request->bearerToken())));

        RateLimiter::for('widget-poll', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('neuraldesk.widgets.poll_rate_per_minute', 60)),
        )->by('widget-poll:'.$request->route('publicWidget').':'.$request->ip().':'.hash('sha256', (string) $request->bearerToken())));
    }
}
