<?php

namespace App\Http\Controllers\Auth;

use App\Actions\UpdateSubscriberPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriberPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Throwable;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdateSubscriberPasswordRequest $request, UpdateSubscriberPassword $updatePassword): RedirectResponse
    {
        try {
            $updatePassword->handle($request->user(), $request->validated('password'));
            $request->session()->regenerate();
        } catch (Throwable $exception) {
            report($exception);

            return Redirect::route('profile.edit')->with('toast', [
                'type' => 'error',
                'title' => __('Password update failed'),
                'message' => __('Your password could not be changed. Please try again.'),
            ]);
        }

        return Redirect::route('profile.edit')
            ->with('status', 'password-updated')
            ->with('toast', [
                'type' => 'success',
                'title' => __('Password updated'),
                'message' => __('Password updated successfully.'),
            ]);
    }
}
