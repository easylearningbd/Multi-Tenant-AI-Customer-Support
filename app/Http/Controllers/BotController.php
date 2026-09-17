<?php

namespace App\Http\Controllers;

use App\Actions\CreateBot;
use App\Http\Requests\StoreBotRequest;
use App\Models\Bot;
use App\Models\User;
use App\Services\BotIndexData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class BotController extends Controller
{
    public function index(Request $request, BotIndexData $indexData): View
    {
        Gate::authorize('viewAny', Bot::class);

        /** @var User $subscriber */
        $subscriber = $request->user();

        return view('bots.index', $indexData->for($subscriber));
    }

    public function store(StoreBotRequest $request, CreateBot $createBot): RedirectResponse
    {
        $attributes = $request->validated();

        try {
            $bot = $createBot->handle($request->user(), $attributes['name']);
        } catch (ValidationException $exception) {
            return redirect()->route('bots.index')
                ->withErrors($exception->errors(), 'createBot')
                ->withInput($request->safe()->only(['name']))
                ->with('toast', [
                    'type' => 'warning',
                    'title' => __('Bot could not be created'),
                    'message' => collect($exception->errors())->flatten()->first(),
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('bots.index')
                ->withInput($request->safe()->only(['name']))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Bot creation failed'),
                    'message' => __('Your bot could not be created. Please try again.'),
                ]);
        }

        return redirect()->route('bots.settings.edit', $bot)->with('toast', [
            'type' => 'success',
            'title' => __('Bot created'),
            'message' => __('Your bot was created as an inactive draft.'),
        ]);
    }

    public function setup(Bot $subscriberBot): RedirectResponse
    {
        Gate::authorize('update', $subscriberBot);

        return redirect()->route('bots.settings.edit', $subscriberBot);
    }
}
