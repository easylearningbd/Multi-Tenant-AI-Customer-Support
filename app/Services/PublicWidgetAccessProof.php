<?php

namespace App\Services;

use App\Enums\WidgetChannel;
use App\Models\Widget;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PublicWidgetAccessProof
{
    public function issue(Widget $widget, string $origin, WidgetChannel $channel): string
    {
        return Crypt::encryptString((string) json_encode([
            'widget' => $widget->public_id,
            'origin' => $origin,
            'channel' => $channel->value,
            'expires_at' => now('UTC')->addMinutes(max(1, (int) config('neuraldesk.widgets.proof_lifetime_minutes', 10)))->timestamp,
            'nonce' => Str::random(24),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{origin: string, channel: WidgetChannel} */
    public function verify(Widget $widget, string $proof): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($proof), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['access_proof' => __('The widget access proof is invalid or expired.')]);
        }

        $publicId = is_array($payload) ? ($payload['widget'] ?? null) : null;
        $origin = is_array($payload) ? ($payload['origin'] ?? null) : null;
        $channel = is_array($payload) ? WidgetChannel::tryFrom((string) ($payload['channel'] ?? '')) : null;
        $expiresAt = is_array($payload) ? (int) ($payload['expires_at'] ?? 0) : 0;
        if (! is_string($publicId) || ! hash_equals($widget->public_id, $publicId)
            || ! is_string($origin) || $origin === '' || ! $channel || $expiresAt < now('UTC')->timestamp) {
            throw ValidationException::withMessages(['access_proof' => __('The widget access proof is invalid or expired.')]);
        }

        return ['origin' => $origin, 'channel' => $channel];
    }
}
