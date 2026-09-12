@extends('admin.layouts.app')

@section('title', __('Users'))

@php
    $currentSort = $filters['sort'] ?? 'created_at';
    $currentDirection = $filters['direction'] ?? 'desc';
    $sortUrl = fn (string $column) => request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc',
        'page' => null,
    ]);
@endphp

@section('content')
    <div class="nd-page-heading">
        <h1>{{ __('Users') }}</h1>
        <p>{{ __('Search and manage subscriber accounts.') }}</p>
    </div>

    <section class="card nd-card nd-users-card" aria-labelledby="users-table-title">
        <div class="card-body">
            <h2 class="visually-hidden" id="users-table-title">{{ __('Subscriber users') }}</h2>

            <form class="nd-users-toolbar" method="GET" action="{{ route('admin.users.index') }}" role="search">
                <div class="nd-users-search">
                    <i class="iconoir-search" aria-hidden="true"></i>
                    <label class="visually-hidden" for="user-search">{{ __('Search subscribers') }}</label>
                    <input class="form-control" id="user-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('Search name, email, or phone...') }}">
                </div>

                <input name="sort" type="hidden" value="{{ $currentSort }}">
                <input name="direction" type="hidden" value="{{ $currentDirection }}">

                <div class="nd-users-toolbar-actions">
                    <label for="users-per-page">{{ __('Show') }}</label>
                    <select class="form-select" id="users-per-page" name="per_page" data-auto-submit>
                        @foreach ([15, 30, 50] as $size)
                            <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span>{{ __('entries') }}</span>
                    <button class="btn nd-btn-primary" type="submit"><i class="iconoir-search" aria-hidden="true"></i>{{ __('Apply') }}</button>
                    @if (! empty($filters['search']))
                        <a class="btn nd-btn-secondary" href="{{ route('admin.users.index') }}">{{ __('Clear') }}</a>
                    @endif
                </div>
            </form>

            @if ($users->isEmpty())
                <div class="nd-users-empty" role="status">
                    <span aria-hidden="true"><i class="iconoir-community"></i></span>
                    <h3>{{ __('No subscribers found') }}</h3>
                    <p>{{ __('Try a different search term or clear the current filters.') }}</p>
                </div>
            @else
                <div class="table-responsive nd-users-table-wrap">
                    <table class="table nd-users-table align-middle">
                        <thead>
                            <tr>
                                @foreach (['name' => __('Name'), 'email' => __('Email')] as $column => $label)
                                    <th scope="col">
                                        <a class="nd-sort-link" href="{{ $sortUrl($column) }}" aria-label="{{ __('Sort by :field', ['field' => $label]) }}">
                                            {{ $label }}
                                            <i class="{{ $currentSort === $column ? ($currentDirection === 'asc' ? 'iconoir-nav-arrow-up' : 'iconoir-nav-arrow-down') : 'iconoir-sort' }}" aria-hidden="true"></i>
                                        </a>
                                    </th>
                                @endforeach
                                <th scope="col">
                                    <a class="nd-sort-link" href="{{ $sortUrl('created_at') }}" aria-label="{{ __('Sort by creation date') }}">
                                        {{ __('Created') }}
                                        <i class="{{ $currentSort === 'created_at' ? ($currentDirection === 'asc' ? 'iconoir-nav-arrow-up' : 'iconoir-nav-arrow-down') : 'iconoir-sort' }}" aria-hidden="true"></i>
                                    </a>
                                </th>
                                <th class="text-end" scope="col">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $subscriber)
                                @php($avatarUrl = $subscriber->avatarUrl())
                                <tr>
                                    <td data-label="{{ __('Name') }}">
                                        <div class="nd-user-identity">
                                            <span class="nd-user-avatar">
                                                @if ($avatarUrl)
                                                    <img src="{{ $avatarUrl }}" alt="">
                                                @else
                                                    <span aria-hidden="true">{{ $subscriber->initials() }}</span>
                                                @endif
                                            </span>
                                            <a href="{{ route('admin.users.show', $subscriber) }}">{{ $subscriber->name }}</a>
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Email') }}"><span class="nd-cell-muted">{{ $subscriber->email }}</span></td>
                                    <td data-label="{{ __('Created') }}">
                                        <time class="nd-cell-muted" datetime="{{ $subscriber->created_at?->toIso8601String() }}">{{ $subscriber->created_at?->timezone(config('app.timezone'))->format('d M, Y \a\t h:i A') ?? __('N/A') }}</time>
                                    </td>
                                    <td class="text-end" data-label="{{ __('Actions') }}">
                                        <div class="nd-row-actions">
                                            <a class="btn nd-icon-action" href="{{ route('admin.users.show', $subscriber) }}" aria-label="{{ __('View :name', ['name' => $subscriber->name]) }}" title="{{ __('View') }}"><i class="iconoir-eye" aria-hidden="true"></i></a>
                                            <a class="btn nd-icon-action" href="{{ route('admin.users.edit', $subscriber) }}" aria-label="{{ __('Edit :name', ['name' => $subscriber->name]) }}" title="{{ __('Edit') }}"><i class="iconoir-edit-pencil" aria-hidden="true"></i></a>
                                            <button class="btn nd-icon-action nd-icon-action-danger" type="button" data-bs-toggle="modal" data-bs-target="#delete-user-modal" data-delete-url="{{ route('admin.users.destroy', $subscriber) }}" data-delete-name="{{ $subscriber->name }}" aria-label="{{ __('Delete :name', ['name' => $subscriber->name]) }}" title="{{ __('Delete') }}"><i class="iconoir-trash" aria-hidden="true"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="nd-users-pagination">
                    <p>{{ __('Showing :first-:last of :total users', ['first' => $users->firstItem(), 'last' => $users->lastItem(), 'total' => $users->total()]) }}</p>
                    {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>

    <div class="modal fade" id="delete-user-modal" tabindex="-1" aria-labelledby="delete-user-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nd-confirm-modal">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="delete-user-modal-title">{{ __('Delete subscriber') }}</h2>
                    <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p>{{ __('Are you sure you want to delete this subscriber?') }} <strong data-delete-user-name></strong></p>
                    <p class="mb-0 text-muted">{{ __('This action may be irreversible and will end the subscriber’s active sessions.') }}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn nd-btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <form method="POST" action="" data-delete-user-form data-submit-once>
                        @csrf
                        @method('DELETE')
                        <button class="btn nd-btn-danger" type="submit" data-submit-button>
                            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span>
                            <span>{{ __('Delete user') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-users.js') }}"></script>
@endpush
