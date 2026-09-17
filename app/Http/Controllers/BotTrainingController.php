<?php

namespace App\Http\Controllers;

use App\Actions\ManageKnowledgeSources;
use App\Http\Requests\DeleteKnowledgeSourceRequest;
use App\Http\Requests\RetrainBotKnowledgeRequest;
use App\Http\Requests\RetrainKnowledgeSourceRequest;
use App\Http\Requests\SyncKnowledgeWebsiteRequest;
use App\Http\Requests\TrainKnowledgeTextRequest;
use App\Http\Requests\UploadKnowledgeFilesRequest;
use App\Models\Bot;
use App\Models\KnowledgeSource;
use App\Services\BotTrainingData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class BotTrainingController extends Controller
{
    public function index(Request $request, Bot $subscriberBot, BotTrainingData $data): View
    {
        Gate::authorize('train', $subscriberBot);

        return view('bots.training', $data->for($request->user(), $subscriberBot));
    }

    public function text(TrainKnowledgeTextRequest $request, Bot $subscriberBot, ManageKnowledgeSources $sources): RedirectResponse
    {
        return $this->attempt(fn () => $sources->createText($request->user(), $subscriberBot, $request->validated('text'), $request->validated('name')), __('Knowledge text queued for training.'));
    }

    public function files(UploadKnowledgeFilesRequest $request, Bot $subscriberBot, ManageKnowledgeSources $sources): RedirectResponse
    {
        return $this->attempt(fn () => $sources->createFiles($request->user(), $subscriberBot, $request->file('files')), __('Files queued for private processing.'));
    }

    public function website(SyncKnowledgeWebsiteRequest $request, Bot $subscriberBot, ManageKnowledgeSources $sources): RedirectResponse
    {
        return $this->attempt(fn () => $sources->createWebsite($request->user(), $subscriberBot, $request->validated('url'), $request->validated('source_type') === 'sitemap', (int) $request->validated('page_limit')), __('Website queued for synchronization.'));
    }

    public function retrain(RetrainKnowledgeSourceRequest $request, Bot $subscriberBot, KnowledgeSource $subscriberSource, ManageKnowledgeSources $sources): RedirectResponse
    {
        return $this->attempt(fn () => $sources->retry($request->user(), $subscriberBot, $subscriberSource), __('Source queued for retraining.'));
    }

    public function retrainAll(RetrainBotKnowledgeRequest $request, Bot $subscriberBot, ManageKnowledgeSources $sources): RedirectResponse
    {
        return $this->attempt(fn () => $sources->retryAll($request->user(), $subscriberBot), __('All sources were queued for retraining.'));
    }

    public function destroy(DeleteKnowledgeSourceRequest $request, Bot $subscriberBot, KnowledgeSource $subscriberSource, ManageKnowledgeSources $sources): RedirectResponse
    {
        return $this->attempt(fn () => $sources->delete($request->user(), $subscriberBot, $subscriberSource), __('Knowledge source deleted.'));
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('toast', ['type' => 'error', 'title' => __('Training request failed'), 'message' => __('The request could not be completed safely. Please try again.')]);
        }

        return back()->with('toast', ['type' => 'success', 'title' => __('Knowledge updated'), 'message' => $message]);
    }
}
