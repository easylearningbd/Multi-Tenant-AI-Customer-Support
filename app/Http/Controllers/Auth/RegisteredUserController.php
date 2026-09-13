<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Exceptions\DefaultTrialUnavailable;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $user = DB::transaction(function () use ($request): User {
                $user = new User;
                $user->name = $request->string('name')->toString();
                $user->email = $request->string('email')->toString();
                $user->password = Hash::make($request->string('password')->toString());
                $user->role = UserRole::USER;
                $user->save();

                event(new Registered($user));

                return $user;
            });
        } catch (DefaultTrialUnavailable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => __('Registration is temporarily unavailable. Please try again later.'),
            ]);
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
