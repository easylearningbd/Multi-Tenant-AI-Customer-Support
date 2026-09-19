<?php

namespace App\Services;

use App\Models\VisitorSession;
use App\Models\Widget;
use Illuminate\Http\Request;

final class PublicWidgetSessionResolver
{
    public function resolve(Request $request, Widget $widget): VisitorSession
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 40 && strlen($token) <= 200, 401);

        $session = VisitorSession::query()->forWidget($widget)->active()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        if ($session->last_seen_at->lt(now('UTC')->subMinute())) {
            $session->forceFill(['last_seen_at' => now('UTC')])->save();
        }

        return $session;
    }
}
