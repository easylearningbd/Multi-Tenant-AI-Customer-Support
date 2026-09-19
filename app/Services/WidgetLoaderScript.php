<?php

namespace App\Services;

use App\Models\Widget;

final class WidgetLoaderScript
{
    public function render(Widget $widget): string
    {
        $frameUrl = route('widgets.frame.show', $widget->public_id);
        $appOrigin = $this->origin((string) config('app.url'));
        $id = $this->json($widget->public_id);
        $src = $this->json($frameUrl);
        $origin = $this->json($appOrigin);
        $side = $this->json($widget->position->value === 'bottom_left' ? 'left' : 'right');

        return <<<JS
(function () {
    'use strict';
    var current = document.currentScript;
    if (!current || document.querySelector('[data-neuraldesk-frame="' + {$id} + '"]')) return;
    var frame = document.createElement('iframe');
    frame.src = {$src};
    frame.title = 'Customer support chat';
    frame.setAttribute('data-neuraldesk-frame', {$id});
    frame.setAttribute('allow', 'clipboard-write');
    frame.setAttribute('sandbox', 'allow-scripts allow-forms allow-same-origin');
    frame.referrerPolicy = 'origin';
    frame.style.position = 'fixed';
    frame.style.bottom = '18px';
    frame.style[{$side}] = '18px';
    frame.style.width = '82px';
    frame.style.height = '82px';
    frame.style.border = '0';
    frame.style.background = 'transparent';
    frame.style.zIndex = '2147483000';
    frame.style.colorScheme = 'normal';
    frame.style.transition = 'width .18s ease, height .18s ease';
    document.body.appendChild(frame);
    window.addEventListener('message', function (event) {
        if (event.source !== frame.contentWindow || event.origin !== {$origin}) return;
        var data = event.data || {};
        if (data.source !== 'neuraldesk-widget' || data.widget !== {$id}) return;
        frame.style.width = data.open ? 'min(420px, calc(100vw - 24px))' : '82px';
        frame.style.height = data.open ? 'min(680px, calc(100vh - 24px))' : '82px';
    });
})();
JS;
    }

    private function json(string $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }
}
