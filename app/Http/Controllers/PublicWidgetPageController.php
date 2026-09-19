<?php

namespace App\Http\Controllers;

use App\Enums\WidgetChannel;
use App\Models\Widget;
use App\Services\PublicWidgetAccessProof;
use App\Services\PublicWidgetContext;
use App\Services\WidgetOriginPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PublicWidgetPageController extends Controller
{
    public function frame(Request $request, string $publicWidget, PublicWidgetContext $context, WidgetOriginPolicy $origins, PublicWidgetAccessProof $proofs): Response
    {
        $widget = $context->resolve($publicWidget);
        $origin = $origins->fromRequest($request);
        abort_unless($origins->allows($widget, $origin), 403);

        return $this->chatResponse($widget, $proofs->issue($widget, $origin, WidgetChannel::EMBEDDED), true, $origin, $origins);
    }

    public function hosted(string $publicWidget, PublicWidgetContext $context, WidgetOriginPolicy $origins, PublicWidgetAccessProof $proofs): Response
    {
        $widget = $context->resolve($publicWidget);
        $origin = $origins->applicationOrigin();

        return $this->chatResponse($widget, $proofs->issue($widget, $origin, WidgetChannel::HOSTED), false, $origin, $origins);
    }

    public function demo(string $publicWidget, PublicWidgetContext $context): Response
    {
        $widget = $context->resolve($publicWidget);

        return response()->view('widgets.demo', [
            'widget' => $widget,
            'loaderUrl' => route('widgets.loader.show', $widget->public_id),
        ])->header('X-Content-Type-Options', 'nosniff');
    }

    private function chatResponse(Widget $widget, string $proof, bool $embedded, string $parentOrigin, WidgetOriginPolicy $origins): Response
    {
        $response = response()->view('widgets.chat', [
            'widget' => $widget,
            'accessProof' => $proof,
            'embedded' => $embedded,
            'parentOrigin' => $parentOrigin,
            'bootstrapUrl' => route('widgets.api.sessions.store', $widget->public_id),
            'prechatUrl' => route('widgets.api.prechat.store', $widget->public_id),
            'messageUrl' => route('widgets.api.messages.store', $widget->public_id),
            'conversationUrlTemplate' => route('widgets.api.conversations.show', [$widget->public_id, '__CONVERSATION__']),
            'handoffUrl' => route('widgets.api.handoff.store', $widget->public_id),
        ]);

        return $response->withHeaders([
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'none'; form-action 'self'; ".$origins->frameAncestors($widget),
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ]);
    }
}
