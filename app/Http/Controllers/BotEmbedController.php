<?php

namespace App\Http\Controllers;

use App\Actions\UpdateWidgetAppearance;
use App\Http\Requests\UpdateWidgetAppearanceRequest;
use App\Models\Bot;
use App\Services\WidgetEmbedData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

final class BotEmbedController extends Controller
{
    public function edit(Bot $subscriberBot, WidgetEmbedData $data): View
    {
        Gate::authorize('manageEmbed', $subscriberBot);

        return view('bots.embed', $data->for(request()->user(), $subscriberBot));
    }

    public function update(
        UpdateWidgetAppearanceRequest $request,
        Bot $subscriberBot,
        WidgetEmbedData $data,
        UpdateWidgetAppearance $updateWidget,
    ): RedirectResponse {
        try {
            $widget = $data->for($request->user(), $subscriberBot)['widget'];
            $updateWidget->handle($request->user(), $subscriberBot, $widget, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('toast', [
                'type' => 'error',
                'title' => __('Widget settings were not saved'),
                'message' => __('The widget appearance could not be saved. Please try again.'),
            ]);
        }

        return redirect()->route('bots.embed.edit', $subscriberBot)->with('toast', [
            'type' => 'success',
            'title' => __('Widget settings updated'),
            'message' => __('The widget appearance and availability settings were saved.'),
        ]);
    }
}
