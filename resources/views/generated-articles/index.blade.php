@extends('layouts.app', ['title' => 'Artículos · Portada'])

@section('content')
<div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-600">Flujo editorial</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight">Artículos</h1>
    <p class="mt-2 text-sm text-slate-500">Consulta las noticias generadas, su validación factual y el estado del proceso.</p>
</div>

<form method="GET" action="{{ route('generated-articles.index') }}" class="mb-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft md:grid-cols-[minmax(16rem,1fr)_auto_auto_auto_auto] md:items-end">
    <div>
        <label for="q" class="mb-2 block text-sm font-medium">Buscar</label>
        <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Titular, entradilla, correo o remitente" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label for="risk" class="mb-2 block text-sm font-medium">Riesgo</label>
        <select id="risk" name="risk" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="">Todos los riesgos</option>
            <option value="pending" @selected(($filters['risk'] ?? '') === 'pending')>Pendiente de validación</option>
            @foreach ($risks as $risk)<option value="{{ $risk->value }}" @selected(($filters['risk'] ?? '') === $risk->value)>{{ $risk->label() }}</option>@endforeach
        </select>
    </div>
    <div>
        <label for="status" class="mb-2 block text-sm font-medium">Estado</label>
        <select id="status" name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="">Todos los estados</option>
            @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach
        </select>
    </div>
    <button class="rounded-lg bg-slate-950 px-5 py-2 text-sm font-semibold text-white">Filtrar</button>
    <a href="{{ route('generated-articles.index') }}" class="px-2 py-2 text-sm text-slate-600 underline">Limpiar</a>
</form>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft">
    <div class="border-b border-slate-100 px-6 py-4"><h2 class="font-semibold">{{ $articles->total() }} artículos encontrados</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-6 py-4">Artículo</th><th class="px-6 py-4">Generado</th><th class="px-6 py-4">Fuente</th><th class="px-6 py-4">Riesgo</th><th class="px-6 py-4">Estado</th><th class="px-6 py-4"><span class="sr-only">Acciones</span></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($articles as $article)
                <tr class="hover:bg-slate-50">
                    <td class="max-w-lg px-6 py-4">
                        <a href="{{ route('press-releases.show', $article->pressRelease) }}#articulo" class="break-words font-semibold text-slate-900 hover:text-brand-600 hover:underline">{{ $article->headline }}</a>
                        <p class="mt-1 line-clamp-1 text-xs text-slate-500">Origen: {{ $article->pressRelease->subject }}</p>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $article->generated_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ $article->pressRelease->pressSource?->name ?? 'Sin fuente asociada' }}</td>
                    <td class="px-6 py-4">
                        @if ($article->validation_risk)
                            <span @class([
                                'inline-block whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold',
                                'bg-emerald-50 text-emerald-800' => $article->validation_risk->value === 'low',
                                'bg-amber-50 text-amber-800' => $article->validation_risk->value === 'medium',
                                'bg-red-50 text-red-800' => $article->validation_risk->value === 'high',
                            ])>{{ $article->validation_risk->label() }}</span>
                        @else
                            <span class="inline-block whitespace-nowrap rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Validación pendiente</span>
                        @endif
                    </td>
                    <td class="px-6 py-4"><span class="inline-block whitespace-nowrap rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">{{ $article->pressRelease->processing_status->label() }}</span></td>
                    <td class="px-6 py-4 text-right"><a href="{{ route('press-releases.show', $article->pressRelease) }}#articulo" class="whitespace-nowrap font-semibold text-brand-600 hover:underline">Ver artículo →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-16 text-center text-slate-500">No hay artículos que mostrar con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-6">{{ $articles->links() }}</div>
@endsection
