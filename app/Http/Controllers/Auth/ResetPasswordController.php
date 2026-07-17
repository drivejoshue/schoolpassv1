<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function show(
        Request $request,
        string $token,
    ): View {
        return view(
            'auth.reset-password',
            [
                'token' => $token,
                'email' => (string) $request->query(
                    'email',
                    ''
                ),
            ]
        );
    }

    public function update(
        Request $request,
    ): RedirectResponse {
        $data = $request->validate([
            'token' => [
                'required',
                'string',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                PasswordRule::min(8)
                    ->letters()
                    ->numbers(),
            ],

            /*
             * Debe tener una regla propia para que Laravel
             * la incluya dentro del arreglo validado.
             */
            'password_confirmation' => [
                'required',
                'string',
            ],
        ]);

        $email = mb_strtolower(
            trim($data['email'])
        );

        /*
         * La recuperación web se limita a cuentas
         * administrativas activas.
         */
        $administrator = User::query()
            ->where('email', $email)
            ->whereIn(
                'role',
                [
                    'superadmin',
                    'school_admin',
                    'director',
                ]
            )
            ->where('status', 'active')
            ->first();

        if ($administrator === null) {
            throw ValidationException::withMessages([
                'email' => (
                    'No fue posible restablecer esta cuenta.'
                ),
            ]);
        }

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $data['password'],
                'password_confirmation' =>
                    $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (
                User $user,
                string $password,
            ): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();

                /*
                 * Revoca tokens móviles anteriores.
                 */
                $user->tokens()->delete();

                event(
                    new PasswordReset($user)
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => trans($status),
            ]);
        }

        return redirect()
            ->route('login')
            ->with(
                'status',
                'La contraseña fue actualizada. '
                .'Ya puedes iniciar sesión.'
            );
    }
}