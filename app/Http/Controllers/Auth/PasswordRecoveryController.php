<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class PasswordRecoveryController extends Controller
{
    public function create()
    {
        return view('auth.recovery');
    }

    public function send(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Si el correo corresponde a una cuenta, recibirás un enlace para restablecer tu contraseña.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.recovery', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes acceder con tu nueva contraseña.')
            : back()->withErrors(['email' => 'El enlace no es válido o ha caducado. Solicita un nuevo enlace de recuperación.'])->withInput($request->only('email'));
    }
}
