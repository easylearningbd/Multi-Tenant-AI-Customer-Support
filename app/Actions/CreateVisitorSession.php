<?php

namespace App\Actions;

use App\DTOs\CreatedVisitorSession;
use App\Models\VisitorSession;
use App\Models\Widget;
use App\Services\PublicWidgetAccessProof;
use App\Services\WidgetOriginPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateVisitorSession
{
    public function __construct(
        private readonly PublicWidgetAccessProof $proofs,
        private readonly WidgetOriginPolicy $origins,
    ) {}

    public function handle(Widget $widget, string $accessProof): CreatedVisitorSession
    {
        $access = $this->proofs->verify($widget, $accessProof);
        abort_unless($this->origins->allows($widget, $access['origin']), 403);

        return DB::transaction(function () use ($widget, $access): CreatedVisitorSession {
            $locked = Widget::query()->whereKey($widget->id)->where('user_id', $widget->user_id)
                ->where('bot_id', $widget->bot_id)->where('is_enabled', true)->lockForUpdate()->firstOrFail();
            $token = Str::random(80);
            $uuid = (string) Str::uuid();
            $session = new VisitorSession;
            $session->uuid = $uuid;
            $session->user_id = $locked->user_id;
            $session->bot_id = $locked->bot_id;
            $session->widget_id = $locked->id;
            $session->token_hash = hash('sha256', $token);
            $session->fill([
                'origin' => $access['origin'],
                'channel' => $access['channel'],
                'visitor_identifier' => 'Visitor '.strtoupper(substr(str_replace('-', '', $uuid), -4)),
                'last_seen_at' => now('UTC'),
                'expires_at' => now('UTC')->addMinutes(max(5, (int) config('neuraldesk.widgets.session_lifetime_minutes', 720))),
            ]);
            $session->save();

            return new CreatedVisitorSession($session, $token);
        }, 3);
    }
}
