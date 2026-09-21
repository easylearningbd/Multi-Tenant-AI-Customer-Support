@extends('subscriber.layouts.app')

@section('title', __('Training'))
@section('header-title', __('Training'))
@section('header-subtitle', $bot->display_name)

@section('header-actions')
    <a class="nd-sub-settings-action secondary" href="{{ route('bots.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
    <a class="nd-sub-settings-action secondary" href="{{ route('bots.embed.edit', $bot) }}"><i class="iconoir-code" aria-hidden="true"></i>{{ __('Embed') }}</a>
    <form method="POST" action="{{ route('bots.training.retrain-all', $bot) }}">
        @csrf
        <button class="nd-sub-training-gradient" type="submit" @disabled($sources->total() === 0)><i class="iconoir-refresh" aria-hidden="true"></i>{{ __('Retrain now') }}</button>
    </form>
@endsection

@section('content')
    <div class="nd-sub-training-page">
        <nav class="nd-sub-training-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('bots.index') }}">{{ __('Bots') }}</a><span>/</span><span>{{ $bot->name }}</span>
        </nav>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>{{ __('Please correct the highlighted fields.') }}</strong>
                <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @unless ($canAddSource)
            <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3" role="status">
                <span>{{ __('Your current plan has no remaining knowledge-source capacity.') }}</span>
                <a class="btn btn-sm btn-dark" href="{{ route('billing.index') }}">{{ __('View plans') }}</a>
            </div>
        @endunless

        <section class="nd-sub-training-text-card" aria-labelledby="knowledge-text-title">
            <div class="nd-sub-training-explainer">
                <span><i class="iconoir-shield-check" aria-hidden="true"></i></span>
                <div><h2 id="knowledge-text-title">{{ __('Reliable knowledge') }}</h2><p>{{ __('Add approved answers, policy details, FAQs, or support notes your bot can trust.') }}</p></div>
                <ul><li>{{ __('Best for verified content') }}</li><li>{{ __('Automatically chunked') }}</li><li>{{ __('Queued immediately') }}</li></ul>
            </div>
            <form method="POST" action="{{ route('bots.training.text.store', $bot) }}">
                @csrf
                <label for="knowledge-text">{{ __('Knowledge text') }}</label>
                <input class="form-control" type="text" name="name" value="{{ old('name') }}" maxlength="180" placeholder="{{ __('Optional source title') }}">
                <textarea class="form-control @error('text') is-invalid @enderror" id="knowledge-text" name="text" rows="8" maxlength="500000" placeholder="{{ __('Example: Refunds are available within 30 days for paid plans.') }}" required>{{ old('text') }}</textarea>
                @error('text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div><small>{{ __('Keep each entry specific so answers stay accurate.') }}</small><button class="nd-sub-training-dark" type="submit" @disabled(! $canAddSource)>{{ __('Train text') }}</button></div>
            </form>
        </section>

        <div class="nd-sub-training-input-grid">
            <section class="nd-sub-training-upload" aria-labelledby="upload-title">
                <form method="POST" action="{{ route('bots.training.files.store', $bot) }}" enctype="multipart/form-data">
                    @csrf
                    <label for="knowledge-files">
                        <i class="iconoir-upload" aria-hidden="true"></i>
                        <strong id="upload-title">{{ __('Drop files to add knowledge') }}</strong>
                        <span>{{ __('PDF, DOCX, MD, TXT, CSV up to 20 MB each') }}</span>
                        <span class="nd-sub-training-browse">{{ __('Browse files') }}</span>
                    </label>
                    <input id="knowledge-files" name="files[]" type="file" accept=".pdf,.docx,.md,.txt,.csv" multiple required data-knowledge-files @disabled(! $canUploadFile)>
                    <p data-selected-files aria-live="polite">{{ __('No files selected') }}</p>
                    <button class="nd-sub-training-dark" type="submit" @disabled(! $canUploadFile)>{{ __('Upload and train') }}</button>
                </form>
            </section>

            <section class="nd-sub-training-site-card" aria-labelledby="sync-title">
                <header><span><i class="iconoir-globe" aria-hidden="true"></i></span><div><h2 id="sync-title">{{ __('Sync a website') }}</h2><p>{{ __('Crawl one public page or a bounded XML sitemap.') }}</p></div></header>
                <form method="POST" action="{{ route('bots.training.website.store', $bot) }}">
                    @csrf
                    <label class="visually-hidden" for="source-type">{{ __('Source type') }}</label>
                    <select class="form-select" id="source-type" name="source_type"><option value="website">{{ __('Website URL') }}</option><option value="sitemap" @selected(old('source_type') === 'sitemap')>{{ __('XML sitemap') }}</option></select>
                    <div class="nd-sub-training-site-fields">
                        <div><label class="visually-hidden" for="website-url">{{ __('Public URL') }}</label><input class="form-control @error('url') is-invalid @enderror" id="website-url" type="url" name="url" value="{{ old('url') }}" placeholder="https://docs.example.com" required>@error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div><label class="visually-hidden" for="page-limit">{{ __('Page limit') }}</label><input class="form-control" id="page-limit" type="number" name="page_limit" min="1" max="{{ config('neuraldesk.knowledge.website.maximum_page_limit') }}" value="{{ old('page_limit', config('neuraldesk.knowledge.website.default_page_limit')) }}" required></div>
                    </div>
                    <button class="nd-sub-training-dark" type="submit" @disabled(! $canAddSource)>{{ __('Sync website') }}</button>
                </form>
            </section>
        </div>

        <section class="nd-sub-training-sources" aria-labelledby="sources-title">
            <header><h2 id="sources-title">{{ __('Sources') }} <span>{{ number_format($sources->total()) }}</span></h2><p>{{ $lastTrainedAt ? __('Trained sources are ready for retrieval.') : __('Training history will appear here.') }}</p></header>
            @if ($sources->isEmpty())
                <div class="nd-sub-training-empty"><i class="iconoir-book-stack" aria-hidden="true"></i><h3>{{ __('No knowledge sources yet') }}</h3><p>{{ __('Add text, upload a private file, or synchronize a public website to begin.') }}</p></div>
            @else
                <div class="table-responsive">
                    <table><thead><tr><th>{{ __('Source') }}</th><th>{{ __('Chunks') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                    @foreach ($sources as $source)
                        <tr>
                            <td><div class="nd-sub-source-name"><span><i class="iconoir-{{ in_array($source->type->value, ['website', 'sitemap']) ? 'globe' : (in_array($source->type->value, ['text', 'txt', 'markdown', 'csv']) ? 'text' : 'page') }}" aria-hidden="true"></i></span><div><strong>{{ $source->name }}</strong><small>{{ $source->type->label() }}</small>@if($source->failure_message)<em>{{ $source->failure_message }}</em>@endif</div></div></td>
                            <td>{{ number_format($source->chunk_count) }}</td>
                            <td><span class="nd-sub-source-status is-{{ $source->status->value }}">{{ $source->status->label() }}</span></td>
                            <td>{{ $source->last_trained_at?->diffForHumans() ?? $source->updated_at->diffForHumans() }}</td>
                            <td><div class="nd-sub-source-actions">
                                <form method="POST" action="{{ route('bots.training.sources.retrain', [$bot, $source]) }}">@csrf<button type="submit" aria-label="{{ __('Retrain :name', ['name' => $source->name]) }}" title="{{ __('Retrain') }}" @disabled($source->isProcessing())><i class="iconoir-refresh" aria-hidden="true"></i></button></form>
                                <button type="button" data-bs-toggle="modal" data-bs-target="#delete-source-{{ $source->id }}" aria-label="{{ __('Delete :name', ['name' => $source->name]) }}" title="{{ __('Delete') }}"><i class="iconoir-trash" aria-hidden="true"></i></button>
                            </div></td>
                        </tr>
                    @endforeach
                    </tbody></table>
                </div>
                <div class="p-3">{{ $sources->links('pagination::bootstrap-5') }}</div>
                @foreach($sources as $source)
                    <div class="modal fade" id="delete-source-{{ $source->id }}" tabindex="-1" aria-labelledby="delete-source-title-{{ $source->id }}" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content nd-sub-delete-bot-modal"><div class="modal-header"><div><h2 id="delete-source-title-{{ $source->id }}">{{ __('Delete knowledge source?') }}</h2><p>{{ $source->name }}</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div><div class="modal-body"><p>{{ __('Its private file and indexed chunks will be removed. This cannot be undone.') }}</p></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button><form method="POST" action="{{ route('bots.training.sources.destroy', [$bot, $source]) }}">@csrf @method('DELETE')<button class="nd-sub-delete-confirm" type="submit">{{ __('Delete source') }}</button></form></div></div></div></div>
                @endforeach
            @endif
        </section>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector('[data-knowledge-files]');
    const feedback = document.querySelector('[data-selected-files]');
    input?.addEventListener('change', () => {
        feedback.textContent = input.files.length ? Array.from(input.files).map(file => file.name).join(', ') : @json(__('No files selected'));
    });
});
</script>
@endpush
