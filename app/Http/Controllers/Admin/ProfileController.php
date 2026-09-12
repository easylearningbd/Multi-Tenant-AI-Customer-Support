<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RemoveAdminAvatar;
use App\Actions\Admin\UpdateAdminPassword;
use App\Actions\Admin\UpdateAdminProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'admin' => $request->user(),
        ]);
    }

    public function update(UpdateAdminProfileRequest $request, UpdateAdminProfile $updateProfile): RedirectResponse
    {
        $validated = $request->validated();
        unset($validated['avatar']);

        try {
            $updateProfile->handle($request->user(), $validated, $request->file('avatar'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->safe()->except('avatar'))
                ->withFragment('profile-information')
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Profile update failed'),
                    'message' => __('Your profile could not be updated. Please try again.'),
                ]);
        }

        return to_route('admin.profile.edit')
            ->withFragment('profile-information')
            ->with('toast', [
                'type' => 'success',
                'title' => __('Profile updated'),
                'message' => __('Profile updated successfully.'),
            ]);
    }

    public function updatePassword(UpdateAdminPasswordRequest $request, UpdateAdminPassword $updatePassword): RedirectResponse
    {
        try {
            $updatePassword->handle($request->user(), $request->validated('password'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withFragment('change-password')
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Password update failed'),
                    'message' => __('Your password could not be changed. Please try again.'),
                ]);
        }

        return to_route('admin.profile.edit')
            ->withFragment('change-password')
            ->with('toast', [
                'type' => 'success',
                'title' => __('Password updated'),
                'message' => __('Password changed successfully.'),
            ]);
    }

    public function destroyAvatar(Request $request, RemoveAdminAvatar $removeAvatar): RedirectResponse
    {
        try {
            $removed = $removeAvatar->handle($request->user());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('toast', [
                'type' => 'error',
                'title' => __('Image removal failed'),
                'message' => __('The profile image could not be removed. Please try again.'),
            ]);
        }

        return to_route('admin.profile.edit')
            ->withFragment('profile-information')
            ->with('toast', [
                'type' => $removed ? 'success' : 'warning',
                'title' => $removed ? __('Profile image removed') : __('No profile image'),
                'message' => $removed
                    ? __('Profile image removed successfully.')
                    : __('There is no profile image to remove.'),
            ]);
    }
}
