@extends('layouts.app', ['title' => 'Correos recibidos · Portada'])

@section('content')
<div class="mb-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-600">Bandeja de entrada</p>
    <h1 class="mt-2 text-3xl font-bold tracking-tight">Correos recibidos</h1>
    <p class="mt-2 text-sm text-slate-500">Consulta los correos importados, sus adjuntos y su estado de procesamiento.</p>
</div>
<form method="GET" action="{{ route('press-releases.index') }}" class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft sm:flex-row sm:items-end">
    <div class="flex-1"><label for="q" class="mb-2 block text-sm font-medium">Buscar</label><input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Asunto, nombre o email del remitente" maxlength="200" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
    <div><label for="status" class="mb-2 block text-sm font-medium">Estado</label><select id="status" name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Todos los estados</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
    <button class="rounded-lg bg-slate-950 px-5 py-2 text-sm font-semibold text-white">Filtrar</button>
    <a href="{{ route('press-releases.index') }}" class="px-2 py-2 text-sm text-slate-600 underline">Limpiar</a>
</form>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft">
    <div class="border-b border-slate-100 px-6 py-4"><h2 class="font-semibold">{{ $releases->total() }} correos encontrados</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-6 py-4">Asunto y remitente</th><th class="px-6 py-4">Recibido</th><th class="px-6 py-4">Fuente</th><th class="px-6 py-4">Artículo</th><th class="px-6 py-4">Estado</th><th class="px-6 py-4">Adjuntos</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($releases as $release)
                <tr class="hover:bg-slate-50">
                    <td class="max-w-md px-6 py-4"><a href="{{ route('press-releases.show', $release) }}" class="break-words font-semibold text-brand-600 hover:underline">{{ $release->subject }}</a><p class="mt-1 break-words text-slate-500">{{ $release->sender_name }} &lt;{{ $release->sender_email }}&gt;</p></td>
                    <td class="whitespace-nowrap px-6 py-4 text-slate-600">{{ $release->received_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-6 py-4 text-slate-600">{{ $release->pressSource?->name ?? 'Sin fuente asociada' }}</td>
                    <td class="px-6 py-4">
                        @if ($release->generatedArticle)
                            <a href="{{ route('press-releases.show', $release) }}#articulo" class="font-semibold text-brand-600 hover:underline">Ver artículo</a>
                            <p class="mt-1 text-xs {{ $release->generatedArticle->validation_risk?->value === 'high' ? 'text-red-700' : 'text-slate-500' }}">{{ $release->generatedArticle->validation_risk?->label() ?? 'Validación pendiente' }}</p>
                        @else
                            <span class="text-slate-400">Sin generar</span>
                        @endif
                    </td>
                    <td class="px-6 py-4"><span class="inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">{{ $release->processing_status->label() }}</span></td>
                    <td class="px-6 py-4 text-slate-600">{{ $release->attachments_count }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-16 text-center text-slate-500">No hay correos que mostrar con estos filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-6">{{ $releases->links() }}</div>
@endsection
