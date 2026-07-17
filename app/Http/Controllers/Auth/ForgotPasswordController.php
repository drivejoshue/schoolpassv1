<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.forgot-password');
    }

    public function send(
        Request $request,
    ): RedirectResponse {
        $data = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        /*
         * La recuperación web institucional se limita
         * a cuentas administrativas.
         */
        $user = User::query()
            ->where('email', mb_strtolower($data['email']))
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

        if ($user !== null) {
            Password::sendResetLink([
                'email' => $user->email,
            ]);
        }

        /*
         * Respuesta genérica para no revelar si
         * una dirección está registrada.
         */
        return back()->with(
            'status',
            'Si el correo corresponde a una cuenta '
            .'administrativa activa, recibirás un enlace '
            .'para restablecer la contraseña.'
        );
    }
}