@extends('layouts.app')

@section('content')
<div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="mb-2 text-xs font-semibold uppercase tracking-[.16em] text-brand-600">Configuración</p><h1 class="text-3xl font-bold tracking-tight text-slate-950">Fuentes de prensa</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Gestiona qué remitentes están autorizados y cómo debe procesarse el contenido que envían.</p></div>
    <a href="{{ route('press-sources.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-950/10 transition hover:-translate-y-0.5 hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>Nueva fuente</a>
</div>

<div class="mb-8 grid grid-cols-2 gap-3 xl:grid-cols-4">
    @foreach ([['Total', $stats['total'], 'bg-slate-100 text-slate-600'], ['Activas', $stats['active'], 'bg-emerald-50 text-emerald-600'], ['Automáticas', $stats['automatic'], 'bg-brand-50 text-brand-600'], ['En revisión', $stats['review'], 'bg-amber-50 text-amber-600']] as [$label, $value, $color])
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-soft sm:p-5"><div class="mb-4 grid h-9 w-9 place-items-center rounded-xl {{ $color }}"><span class="h-2 w-2 rounded-full bg-current"></span></div><p class="text-2xl font-bold tracking-tight text-slate-950">{{ $value }}</p><p class="mt-1 text-xs font-medium text-slate-500 sm:text-sm">{{ $label }}</p></div>
    @endforeach
</div>

<div class="hidden overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-soft md:block">
    <div class="border-b border-slate-100 px-6 py-4"><h2 class="font-semibold text-slate-900">Directorio de fuentes</h2><p class="mt-1 text-xs text-slate-500">Ordenado por prioridad de procesamiento</p></div>
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500"><tr><th class="px-6 py-3.5">Fuente</th><th class="px-5 py-3.5">Coincidencia</th><th class="px-5 py-3.5">Procesamiento</th><th class="px-5 py-3.5 text-center">Prioridad</th><th class="px-5 py-3.5">Estado</th><th class="px-6 py-3.5"><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($sources as $source)
            @php
                $mode = match($source->processing_mode->value) { 'automatic' => ['Automático','bg-brand-50 text-brand-700 ring-brand-600/10'], 'process_only' => ['Solo procesar','bg-blue-50 text-blue-700 ring-blue-600/10'], 'review' => ['Revisión','bg-amber-50 text-amber-700 ring-amber-600/10'], default => ['Ignorar','bg-slate-100 text-slate-600 ring-slate-500/10'] };
            @endphp
            <tr class="group transition hover:bg-slate-50/70">
                <td class="px-6 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 font-bold text-slate-600">{{ mb_strtoupper(mb_substr($source->name, 0, 1)) }}</span><div><p class="font-semibold text-slate-900">{{ $source->name }}</p><p class="mt-0.5 text-xs text-slate-400">{{ $source->default_category ?: 'Sin categoría' }}</p></div></div></td>
                <td class="px-5 py-4"><p class="font-mono text-xs text-slate-700">{{ $source->email ?? '@'.$source->domain }}</p><p class="mt-1 text-xs text-slate-400">{{ $source->match_type->value === 'exact_email' ? 'Email exacto' : 'Dominio' }}</p></td>
                <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $mode[1] }}">{{ $mode[0] }}</span></td>
                <td class="px-5 py-4 text-center"><span class="inline-grid h-8 min-w-8 place-items-center rounded-lg bg-slate-100 px-2 text-xs font-bold text-slate-600">{{ $source->priority }}</span></td>
                <td class="px-5 py-4"><span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $source->is_active ? 'text-emerald-700' : 'text-slate-400' }}"><span class="h-2 w-2 rounded-full {{ $source->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>{{ $source->is_active ? 'Activa' : 'Inactiva' }}</span></td>
                <td class="px-6 py-4"><div class="flex justify-end gap-1"><a aria-label="Editar {{ $source->name }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-white hover:text-slate-900 hover:shadow" href="{{ route('press-sources.edit', $source) }}"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg></a><form method="POST" action="{{ route('press-sources.destroy', $source) }}" onsubmit="return confirm('¿Eliminar esta fuente?')">@csrf @method('DELETE')<button aria-label="Eliminar {{ $source->name }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-red-50 hover:text-red-600"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.1 1.02.16m-1.02-.16L18.16 19.67A2.25 2.25 0 0 1 15.91 21H8.09a2.25 2.25 0 0 1-2.24-2.33L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .56c.34-.06.68-.11 1.02-.16m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a52.1 52.1 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.7 48.7 0 0 0-7.5 0"/></svg></button></form></div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-6 py-16 text-center"><div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M18 18.72a9.1 9.1 0 0 0 3.74-.48 3 3 0 0 0-4.68-2.51m.94 2.99v.01c0 .22-.01.44-.03.65A11.95 11.95 0 0 1 12 21c-2.17 0-4.2-.58-5.97-1.61A6.06 6.06 0 0 1 6 18.72m12 0a5.97 5.97 0 0 0-.94-2.99m0 0A6 6 0 0 0 6.94 15.73m0 0A3 3 0 0 0 2.26 18.24a9.1 9.1 0 0 0 3.74.48m.94-2.99A6 6 0 0 0 6 18.72M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg></div><p class="mt-4 font-semibold text-slate-800">Todavía no hay fuentes</p><p class="mt-1 text-sm text-slate-500">Añade el primer remitente autorizado para empezar.</p><a href="{{ route('press-sources.create') }}" class="mt-5 inline-flex text-sm font-semibold text-brand-600 hover:text-brand-700">Crear primera fuente →</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="space-y-3 md:hidden">
    @forelse($sources as $source)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-soft"><div class="flex items-start justify-between gap-4"><div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 font-bold text-slate-600">{{ mb_strtoupper(mb_substr($source->name, 0, 1)) }}</span><div class="min-w-0"><p class="truncate font-semibold">{{ $source->name }}</p><p class="truncate font-mono text-xs text-slate-500">{{ $source->email ?? '@'.$source->domain }}</p></div></div><span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $source->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span></div><div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4"><span class="text-xs text-slate-500">{{ $source->processing_mode->value }} · Prioridad {{ $source->priority }}</span><div class="flex gap-3"><a class="text-sm font-semibold text-slate-700" href="{{ route('press-sources.edit', $source) }}">Editar</a><form method="POST" action="{{ route('press-sources.destroy', $source) }}" onsubmit="return confirm('¿Eliminar esta fuente?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600">Eliminar</button></form></div></div></div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Todavía no hay fuentes configuradas.</div>
    @endforelse
</div>

@if($sources->hasPages())<div class="mt-6">{{ $sources->links() }}</div>@endif
@endsection
