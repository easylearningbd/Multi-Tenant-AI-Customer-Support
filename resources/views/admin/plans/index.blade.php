@extends('admin.layouts.app')

@section('title', __('Subscription Plans'))

@section('content')
    <div class="nd-page-heading nd-page-heading-actions">
        <div><h1>{{ __('Subscription Plans') }}</h1><p>{{ __('Manage global pricing, publishing, features, and usage limits.') }}</p></div>
        <a class="btn nd-btn-primary" href="{{ route('admin.plans.create') }}"><i class="iconoir-plus-circle" aria-hidden="true"></i>{{ __('Add Plan') }}</a>
    </div>

    <section class="card nd-card nd-plans-card" aria-labelledby="plans-list-title">
        <div class="card-body">
            <h2 class="visually-hidden" id="plans-list-title">{{ __('Available subscription plan records') }}</h2>

            @if ($plans->isEmpty())
                <div class="nd-users-empty" role="status">
                    <span aria-hidden="true"><i class="iconoir-database"></i></span>
                    <h3>{{ __('No subscription plans') }}</h3>
                    <p>{{ __('Create the first plan to configure pricing and usage limits.') }}</p>
                </div>
            @else
                <div class="table-responsive nd-plans-table-wrap">
                    <table class="table nd-plans-table align-middle">
                        <thead><tr>
                            <th scope="col">{{ __('Plan') }}</th>
                            <th scope="col">{{ __('Price') }}</th>
                            <th scope="col">{{ __('Limits') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Sort') }}</th>
                            <th class="text-end" scope="col">{{ __('Actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr>
                                    <td data-label="{{ __('Plan') }}">
                                        <div class="nd-plan-name"><a href="{{ route('admin.plans.show', $plan) }}">{{ $plan->name }}</a><span>{{ $plan->slug }}</span></div>
                                    </td>
                                    <td data-label="{{ __('Price') }}">
                                        <div class="nd-plan-price"><strong>{{ $plan->formattedPrice() }}</strong><span>{{ $plan->custom_pricing ? __('Custom') : $plan->interval->label() }}</span></div>
                                    </td>
                                    <td data-label="{{ __('Limits') }}">
                                        <div class="nd-limit-badges">
                                            @foreach (\App\Models\Plan::LIMITS as $key => $label)
                                                <span>{{ __($label) }}: <strong>{{ $plan->hasUnlimitedLimit($key) ? __('Unlimited') : number_format($plan->limitFor($key)) }}</strong></span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td data-label="{{ __('Status') }}">
                                        <form method="POST" action="{{ route('admin.plans.status.update', $plan) }}" data-submit-once>
                                            @csrf
                                            @method('PATCH')
                                            <input name="is_active" type="hidden" value="{{ $plan->is_active ? 0 : 1 }}">
                                            <button class="nd-status-badge {{ $plan->is_active ? 'nd-status-success' : 'nd-status-muted' }}" type="submit" title="{{ $plan->is_active ? __('Deactivate plan') : __('Activate plan') }}" aria-label="{{ $plan->is_active ? __('Deactivate :plan', ['plan' => $plan->name]) : __('Activate :plan', ['plan' => $plan->name]) }}">
                                                {{ $plan->is_active ? __('Active') : __('Inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td data-label="{{ __('Sort') }}">{{ $plan->sort_order }}</td>
                                    <td class="text-end" data-label="{{ __('Actions') }}">
                                        <div class="nd-row-actions">
                                            <a class="btn nd-icon-action" href="{{ route('admin.plans.show', $plan) }}" aria-label="{{ __('View :plan', ['plan' => $plan->name]) }}" title="{{ __('View') }}"><i class="iconoir-eye" aria-hidden="true"></i></a>
                                            <a class="btn nd-icon-action" href="{{ route('admin.plans.edit', $plan) }}" aria-label="{{ __('Edit :plan', ['plan' => $plan->name]) }}" title="{{ __('Edit') }}"><i class="iconoir-edit-pencil" aria-hidden="true"></i></a>
                                            @if (! $referencedPlans[$plan->id])
                                                <button class="btn nd-icon-action nd-icon-action-danger" type="button" data-bs-toggle="modal" data-bs-target="#delete-plan-modal" data-delete-url="{{ route('admin.plans.destroy', $plan) }}" data-delete-name="{{ $plan->name }}" aria-label="{{ __('Delete :plan', ['plan' => $plan->name]) }}" title="{{ __('Delete') }}"><i class="iconoir-trash" aria-hidden="true"></i></button>
                                            @else
                                                <button class="btn nd-icon-action" type="button" disabled aria-label="{{ __('Cannot delete :plan because it has billing history', ['plan' => $plan->name]) }}" title="{{ __('Deactivate this referenced plan instead of deleting it.') }}"><i class="iconoir-lock" aria-hidden="true"></i></button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($plans->hasPages())
                    <div class="nd-users-pagination">
                        <p>{{ __('Showing :first-:last of :total plans', ['first' => $plans->firstItem(), 'last' => $plans->lastItem(), 'total' => $plans->total()]) }}</p>
                        {{ $plans->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            @endif
        </div>
    </section>

    <div class="modal fade" id="delete-plan-modal" tabindex="-1" aria-labelledby="delete-plan-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content nd-confirm-modal">
            <div class="modal-header"><h2 class="modal-title fs-5" id="delete-plan-title">{{ __('Delete plan') }}</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body"><p>{{ __('Are you sure you want to permanently delete') }} <strong data-delete-plan-name></strong>?</p><p class="mb-0 text-muted">{{ __('This is allowed only while no subscription or billing history references the plan.') }}</p></div>
            <div class="modal-footer">
                <button class="btn nd-btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <form method="POST" action="" data-delete-plan-form data-submit-once>@csrf @method('DELETE')<button class="btn nd-btn-danger" type="submit" data-submit-button><span class="spinner-border spinner-border-sm d-none" data-submit-spinner aria-hidden="true"></span>{{ __('Delete plan') }}</button></form>
            </div>
        </div></div>
    </div>
@endsection

@push('scripts')<script src="{{ asset('js/admin-plans.js') }}"></script>@endpush
