<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\DeleteSubscriberUser;
use App\Actions\Admin\UpdateSubscriberUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\Admin\UserIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

final class UserController extends Controller
{
    public function index(ListUsersRequest $request, UserIndexQuery $query): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => $query->paginate($request),
            'filters' => $request->validated(),
        ]);
    }

    public function show(User $subscriber): View
    {
        Gate::authorize('view', $subscriber);

        return view('admin.users.show', ['subscriber' => $subscriber]);
    }

    public function edit(User $subscriber): View
    {
        Gate::authorize('update', $subscriber);

        return view('admin.users.edit', ['subscriber' => $subscriber]);
    }

    public function update(
        UpdateUserRequest $request,
        User $subscriber,
        UpdateSubscriberUser $updateSubscriber,
    ): RedirectResponse {
        try {
            $attributes = $request->safe()->only(['name', 'email', 'phone']);
            $updateSubscriber->handle($subscriber, $attributes, $request->file('avatar'));

            Log::info('Admin updated a subscriber account.', [
                'actor_user_id' => $request->user()->id,
                'subscriber_user_id' => $subscriber->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->safe()->except('avatar'))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Update failed'),
                    'message' => __('The user could not be updated. Please try again.'),
                ]);
        }

        return redirect()
            ->route('admin.users.show', $subscriber)
            ->with('toast', [
                'type' => 'success',
                'title' => __('User updated'),
                'message' => __('User updated successfully.'),
            ]);
    }

    public function destroy(Request $request, User $subscriber, DeleteSubscriberUser $deleteSubscriber): RedirectResponse
    {
        Gate::authorize('delete', $subscriber);

        try {
            $subscriberId = $subscriber->id;
            $deleteSubscriber->handle($subscriber);

            Log::info('Admin deleted a subscriber account.', [
                'actor_user_id' => $request->user()->id,
                'subscriber_user_id' => $subscriberId,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => __('Deletion failed'),
                'message' => __('The user could not be deleted because the account still has protected related data.'),
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('toast', [
                'type' => 'success',
                'title' => __('User deleted'),
                'message' => __('User deleted successfully.'),
            ]);
    }
}
