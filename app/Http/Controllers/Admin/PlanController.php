<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeletePlan;
use App\Actions\Admin\SavePlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Requests\Admin\UpdatePlanRequest;
use App\Http\Requests\Admin\UpdatePlanStatusRequest;
use App\Models\Plan;
use App\Services\PlanDeletionGuard;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class PlanController extends Controller
{
    public function index(PlanDeletionGuard $deletionGuard): View
    {
        Gate::authorize('viewAny', Plan::class);

        $plans = Plan::query()->ordered()->paginate(15);

        return view('admin.plans.index', [
            'plans' => $plans,
            'referencedPlans' => $plans->getCollection()
                ->mapWithKeys(fn (Plan $plan): array => [$plan->id => $deletionGuard->isReferenced($plan)]),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Plan::class);

        return view('admin.plans.create', ['plan' => new Plan]);
    }

    public function store(StorePlanRequest $request, SavePlan $savePlan): RedirectResponse
    {
        try {
            $plan = $savePlan->create($request->validated());
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('toast', [
                'type' => 'error',
                'title' => __('Creation failed'),
                'message' => __('The plan could not be created. Please try again.'),
            ]);
        }

        Log::info('Admin created a subscription plan.', [
            'actor_user_id' => $request->user()->id,
            'plan_id' => $plan->id,
        ]);

        return redirect()->route('admin.plans.show', $plan)->with('toast', [
            'type' => 'success',
            'title' => __('Plan created'),
            'message' => __('Plan created successfully.'),
        ]);
    }

    public function show(Plan $plan, PlanDeletionGuard $deletionGuard): View
    {
        Gate::authorize('view', $plan);

        return view('admin.plans.show', [
            'plan' => $plan,
            'isReferenced' => $deletionGuard->isReferenced($plan),
        ]);
    }

    public function edit(Plan $plan, PlanDeletionGuard $deletionGuard): View
    {
        Gate::authorize('update', $plan);

        return view('admin.plans.edit', [
            'plan' => $plan,
            'isReferenced' => $deletionGuard->isReferenced($plan),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan, SavePlan $savePlan): RedirectResponse
    {
        try {
            $savePlan->update($plan, $request->validated());
        } catch (DomainException) {
            return back()->withInput()->with('toast', [
                'type' => 'warning',
                'title' => __('Update blocked'),
                'message' => __('Pricing, currency, interval, and custom-pricing settings cannot change while billing records reference this plan.'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('toast', [
                'type' => 'error',
                'title' => __('Update failed'),
                'message' => __('The plan could not be updated. Please try again.'),
            ]);
        }

        Log::info('Admin updated a subscription plan.', [
            'actor_user_id' => $request->user()->id,
            'plan_id' => $plan->id,
        ]);

        return redirect()->route('admin.plans.show', $plan)->with('toast', [
            'type' => 'success',
            'title' => __('Plan updated'),
            'message' => __('Plan updated successfully.'),
        ]);
    }

    public function updateStatus(UpdatePlanStatusRequest $request, Plan $plan): RedirectResponse
    {
        $plan->is_active = $request->boolean('is_active');
        $plan->save();

        Log::info('Admin changed a subscription plan status.', [
            'actor_user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'is_active' => $plan->is_active,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => $plan->is_active ? __('Plan activated') : __('Plan deactivated'),
            'message' => $plan->is_active ? __('Plan activated successfully.') : __('Plan deactivated successfully.'),
        ]);
    }

    public function destroy(Plan $plan, DeletePlan $deletePlan): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        try {
            $planId = $plan->id;
            $deletePlan->handle($plan);
        } catch (DomainException) {
            return back()->with('toast', [
                'type' => 'warning',
                'title' => __('Deletion blocked'),
                'message' => __('This plan has subscription or billing history. Deactivate it instead of deleting it.'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => __('Deletion failed'),
                'message' => __('The plan could not be deleted safely.'),
            ]);
        }

        Log::info('Admin deleted a subscription plan.', [
            'actor_user_id' => auth()->id(),
            'plan_id' => $planId,
        ]);

        return redirect()->route('admin.plans.index')->with('toast', [
            'type' => 'success',
            'title' => __('Plan deleted'),
            'message' => __('Plan deleted successfully.'),
        ]);
    }
}
