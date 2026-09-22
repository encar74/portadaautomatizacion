<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Portada · Automatización' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{brand:{50:'#fff1f4',100:'#ffe4eb',500:'#bd1b42',600:'#a61538',700:'#89112e'}},boxShadow:{soft:'0 1px 2px rgba(15,23,42,.04), 0 10px 30px rgba(15,23,42,.06)'}}}}</script>
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-900 antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[256px_1fr]">
    <aside class="hidden border-r border-slate-800 bg-slate-950 text-white lg:flex lg:flex-col">
        <div class="flex h-24 items-center border-b border-white/10 px-5">
            <div class="rounded-lg bg-white px-3 py-2 shadow-lg shadow-black/20"><img src="{{ asset('images/logo-portada.png') }}" alt="Portada.info" class="h-auto w-44"></div>
        </div>
        <nav class="flex-1 px-4 py-6">
            <p class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[.16em] text-slate-500">Contenido</p>
            <a href="{{ route('press-releases.index') }}" @if(request()->routeIs('press-releases.*')) aria-current="page" @endif class="mb-6 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('press-releases.*') ? 'bg-white/10 ring-1 ring-white/10' : 'text-slate-300 hover:bg-white/5' }}">
                <span aria-hidden="true" class="text-brand-500">✉</span> Correos recibidos
            </a>
            <p class="mb-3 px-3 text-[11px] font-semibold uppercase tracking-[.16em] text-slate-500">Configuración</p>
            <a href="{{ route('press-sources.index') }}" @if(request()->routeIs('press-sources.*')) aria-current="page" @endif class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium {{ request()->routeIs('press-sources.*') ? 'bg-white/10 ring-1 ring-white/10' : 'text-slate-300 hover:bg-white/5' }}">
                <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 0 0-5-3.87M17 20H7m10 0v-2c0-1.04-.2-2.03-.56-2.94M7 20H2v-2a4 4 0 0 1 5-3.87M7 20v-2c0-1.04.2-2.03.56-2.94m8.88 0a5 5 0 0 0-8.88 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                Fuentes de prensa
            </a>
        </nav>
        <div class="border-t border-white/10 p-4">
            <div class="mb-3 flex items-center gap-3 px-2">
                <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-800 text-sm font-bold text-brand-500">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                <div class="min-w-0"><p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p></div>
            </div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-sm text-slate-400 transition hover:bg-white/5 hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-3H9m9.75 0-3-3m3 3-3 3"/></svg>Cerrar sesión</button></form>
        </div>
    </aside>
    <div class="min-w-0">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur md:px-8 lg:hidden">
            <a href="{{ route('press-sources.index') }}" class="block rounded bg-white"><img src="{{ asset('images/logo-portada.png') }}" alt="Portada.info" class="h-auto w-36"></a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button aria-label="Cerrar sesión" class="rounded-lg border border-slate-200 p-2 text-slate-500"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-3H9m9.75 0-3-3m3 3-3 3"/></svg></button></form>
        </header>
        <nav aria-label="Secciones" class="flex gap-4 border-b border-slate-200 bg-white px-4 py-3 text-sm lg:hidden">
            <a href="{{ route('press-releases.index') }}" class="{{ request()->routeIs('press-releases.*') ? 'font-semibold text-brand-600' : 'text-slate-600' }}">Correos recibidos</a>
            <a href="{{ route('press-sources.index') }}" class="{{ request()->routeIs('press-sources.*') ? 'font-semibold text-brand-600' : 'text-slate-600' }}">Fuentes de prensa</a>
        </nav>
        <main class="mx-auto max-w-7xl px-4 py-7 sm:px-6 md:px-8 lg:py-10">
            @if (session('status'))<div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm"><svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 12.75 6 6 9-13.5"/></svg>{{ session('status') }}</div>@endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
