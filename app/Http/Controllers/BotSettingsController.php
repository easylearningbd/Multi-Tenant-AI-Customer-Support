<?php

namespace App\Http\Controllers;

use App\Actions\DeleteBot;
use App\Actions\UpdateBotConfiguration;
use App\Http\Requests\UpdateBotSettingsRequest;
use App\Models\Bot;
use App\Services\BotSettingsData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

final class BotSettingsController extends Controller
{
    public function edit(Bot $subscriberBot, BotSettingsData $settingsData): View
    {
        Gate::authorize('update', $subscriberBot);

        return view('bots.settings', $settingsData->for($subscriberBot));
    }

    public function update(
        UpdateBotSettingsRequest $request,
        Bot $subscriberBot,
        UpdateBotConfiguration $updateBot,
    ): RedirectResponse {
        try {
            $updateBot->handle($request->user(), $subscriberBot, $request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput($request->safe()->except(['model_override']))->with('toast', [
                'type' => 'error',
                'title' => __('Settings were not saved'),
                'message' => __('The bot settings could not be saved. Please try again.'),
            ]);
        }

        return redirect()->route('bots.settings.edit', $subscriberBot)->with('toast', [
            'type' => 'success',
            'title' => __('Bot settings updated'),
            'message' => __('Identity, behavior, and visitor form settings were saved.'),
        ]);
    }

    public function destroy(Request $request, Bot $subscriberBot, DeleteBot $deleteBot): RedirectResponse
    {
        Gate::authorize('delete', $subscriberBot);

        try {
            $deleteBot->handle($request->user(), $subscriberBot);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => __('Bot was not deleted'),
                'message' => __('The bot could not be deleted safely. Please try again.'),
            ]);
        }

        return redirect()->route('bots.index')->with('toast', [
            'type' => 'success',
            'title' => __('Bot deleted'),
            'message' => __('The bot is inactive and has been removed from your workspace.'),
        ]);
    }
}
