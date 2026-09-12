<?php

namespace App\Http\Controllers;

use App\Actions\RemoveSubscriberAvatar;
use App\Actions\UpdateSubscriberProfile;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UpdateSubscriberProfile $updateProfile): RedirectResponse
    {
        $validated = $request->validated();
        unset($validated['avatar']);

        try {
            $updateProfile->handle($request->user(), $validated, $request->file('avatar'));
        } catch (Throwable $exception) {
            report($exception);

            return Redirect::route('profile.edit')
                ->withInput($request->safe()->except('avatar'))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Profile update failed'),
                    'message' => __('Your profile could not be updated. Please try again.'),
                ]);
        }

        $hasAvatar = $request->hasFile('avatar');

        return Redirect::route('profile.edit')
            ->with('status', 'profile-updated')
            ->with('toast', [
                'type' => 'success',
                'title' => $hasAvatar ? __('Avatar updated') : __('Profile updated'),
                'message' => $hasAvatar
                    ? __('Avatar updated successfully.')
                    : __('Profile updated successfully.'),
            ]);
    }

    /**
     * Remove the authenticated subscriber's custom avatar.
     */
    public function destroyAvatar(Request $request, RemoveSubscriberAvatar $removeAvatar): RedirectResponse
    {
        try {
            $removed = $removeAvatar->handle($request->user());
        } catch (Throwable $exception) {
            report($exception);

            return Redirect::route('profile.edit')->with('toast', [
                'type' => 'error',
                'title' => __('Avatar removal failed'),
                'message' => __('Your avatar could not be removed. Please try again.'),
            ]);
        }

        return Redirect::route('profile.edit')->with('toast', [
            'type' => $removed ? 'success' : 'warning',
            'title' => $removed ? __('Avatar removed') : __('No custom avatar'),
            'message' => $removed
                ? __('Avatar removed successfully.')
                : __('There is no custom avatar to remove.'),
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
