<?php

namespace App\Services;

use App\Actions\EnsureBotWidget;
use App\Models\Bot;
use App\Models\User;
use Illuminate\Support\Facades\Route;

final class WidgetEmbedData
{
    public function __construct(private readonly EnsureBotWidget $ensureBotWidget) {}

    /** @return array<string, mixed> */
    public function for(User $owner, Bot $bot): array
    {
        abort_unless($bot->user_id === $owner->id, 404);

        $widget = $this->ensureBotWidget->handle($bot)->load(['bot', 'domains']);
        $publicId = rawurlencode($widget->public_id);
        $loaderUrl = url(sprintf((string) config('neuraldesk.widgets.loader_path'), $publicId));
        $hostedUrl = url(sprintf((string) config('neuraldesk.widgets.hosted_path'), $publicId));
        $demoUrl = url(sprintf((string) config('neuraldesk.widgets.demo_path'), $publicId));

        return [
            'bot' => $bot,
            'widget' => $widget,
            'accentColors' => (array) config('neuraldesk.widgets.accent_colors', []),
            'loaderUrl' => $loaderUrl,
            'hostedUrl' => $hostedUrl,
            'demoUrl' => $demoUrl,
            'embedCode' => sprintf(
                '<script src="%s" data-neuraldesk-widget="%s" defer></script>',
                htmlspecialchars($loaderUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($widget->public_id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ),
            'hostedAvailable' => Route::has('widgets.hosted.show'),
            'demoAvailable' => Route::has('widgets.demo.show'),
            'allowedOriginsText' => $widget->domains->pluck('origin')->implode(PHP_EOL),
        ];
    }
}
