<?php

namespace App\Services;

use App\Models\Widget;
use Illuminate\Http\Request;

final class WidgetOriginPolicy
{
    public function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $path = (string) ($parts['path'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)
            || $host === ''
            || ! in_array($path, ['', '/'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        $hostForOrigin = str_contains($host, ':') ? '['.$host.']' : $host;

        return $scheme.'://'.$hostForOrigin.($port ? ':'.$port : '');
    }

    public function fromRequest(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (is_string($origin) && strtolower($origin) !== 'null') {
            return $this->normalize($origin);
        }

        $referer = $request->headers->get('Referer');
        if (! is_string($referer) || filter_var($referer, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $parts = parse_url($referer);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $host = str_contains((string) $parts['host'], ':') ? '['.$parts['host'].']' : $parts['host'];
        $candidate = $parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $this->normalize($candidate);
    }

    public function applicationOrigin(): string
    {
        return $this->normalize((string) config('app.url')) ?? 'http://localhost';
    }

    public function allows(Widget $widget, ?string $origin): bool
    {
        $normalized = $this->normalize($origin);
        if ($normalized === null) {
            return false;
        }
        if (hash_equals($this->applicationOrigin(), $normalized)) {
            return true;
        }

        return $widget->domains()->where('user_id', $widget->user_id)
            ->where('bot_id', $widget->bot_id)
            ->where('origin', $normalized)
            ->exists();
    }

    public function frameAncestors(Widget $widget): string
    {
        $origins = $widget->domains()->pluck('origin')->push($this->applicationOrigin())->unique()->values();

        return "frame-ancestors 'self' ".$origins->implode(' ');
    }
}
