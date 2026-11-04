<!DOCTYPE html>
<html lang="es" class="h-full">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso · Portada Automatización</title><script src="https://cdn.tailwindcss.com"></script><script>tailwind.config={theme:{extend:{colors:{brand:{50:'#fff1f4',100:'#ffe4eb',300:'#e893a8',400:'#d94e70',500:'#bd1b42',600:'#a61538',700:'#89112e',950:'#3c0715'}}}}}</script></head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center px-6 py-12">
<main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
<img src="{{ asset('images/logo-portada.png') }}" alt="Portada.info" class="mb-8 w-52">
<h1 class="text-2xl font-bold text-slate-950">{{ isset($token) ? 'Nueva contraseña' : 'Recuperar contraseña' }}</h1>
<p class="mt-3 text-sm text-slate-500">{{ isset($token) ? 'Elige una contraseña de al menos 12 caracteres.' : 'Introduce tu correo y te enviaremos un enlace para recuperar el acceso.' }}</p>
@if(session('status'))<p role="status" class="mt-5 rounded-xl bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ isset($token) ? route('password.update') : route('password.email') }}" class="mt-6 space-y-5">
@csrf
@isset($token)<input type="hidden" name="token" value="{{ $token }}">@endisset
<label class="block text-sm font-semibold">Correo electrónico<input type="email" name="email" value="{{ old('email', $email ?? '') }}" required autocomplete="email" class="mt-2 w-full rounded-xl border border-slate-300 p-3"></label>
@isset($token)
<label class="block text-sm font-semibold">Nueva contraseña<input type="password" name="password" required minlength="12" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 p-3"></label>
<label class="block text-sm font-semibold">Repite la contraseña<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="mt-2 w-full rounded-xl border border-slate-300 p-3"></label>
@endisset
<button class="w-full rounded-xl bg-slate-950 p-3 font-semibold text-white hover:bg-slate-800">{{ isset($token) ? 'Guardar contraseña' : 'Enviar enlace de recuperación' }}</button>
</form>
<a href="{{ route('login') }}" class="mt-6 inline-block text-sm font-semibold text-brand-600 hover:underline">Volver al acceso</a>
@isset($token)<a href="{{ route('password.request') }}" class="mt-4 block text-sm text-brand-600 hover:underline">Solicitar un nuevo enlace</a>@endisset
</main></body></html>
