@extends('layouts.app', ['title' => 'Detalle del correo · Portada'])

@section('content')
<a href="{{ route('press-releases.index') }}" class="text-sm font-medium text-brand-600 hover:underline">← Correos recibidos</a>
<h1 class="mt-5 break-words text-2xl font-bold tracking-tight sm:text-3xl">{{ $pressRelease->subject }}</h1>
<div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
    <dl class="grid gap-5 text-sm sm:grid-cols-2">
        <div><dt class="text-slate-500">Remitente</dt><dd class="mt-1 break-words font-medium">{{ $pressRelease->sender_name }} &lt;{{ $pressRelease->sender_email }}&gt;</dd></div>
        <div><dt class="text-slate-500">Recibido</dt><dd class="mt-1">{{ $pressRelease->received_at?->format('d/m/Y H:i') }}</dd></div>
        <div><dt class="text-slate-500">Fuente de prensa</dt><dd class="mt-1">@if ($pressRelease->pressSource)<a class="text-brand-600 hover:underline" href="{{ route('press-sources.edit', $pressRelease->pressSource) }}">{{ $pressRelease->pressSource->name }}</a>@else Sin fuente asociada @endif</dd></div>
        <div><dt class="text-slate-500">Estado</dt><dd class="mt-1 font-medium">{{ $pressRelease->processing_status->label() }}</dd></div>
    </dl>
    @if (! $pressRelease->pressSource)
        <div class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Este correo no tiene una fuente asociada. Puedes configurar su remitente en <a href="{{ route('press-sources.create') }}" class="font-semibold underline">Nueva fuente</a> para futuras importaciones. Crear la fuente no reasigna los correos ya importados.</div>
    @endif
    @if ($pressRelease->error_message)<p class="mt-5 break-words rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ $pressRelease->error_message }}</p>@endif
</div>
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
    <h2 class="text-lg font-semibold">Contenido del correo</h2>
    @if (filled($body))<div class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ $body }}</div>@else<p class="mt-4 text-sm text-slate-500">Este correo no contiene texto para mostrar. Revisa sus adjuntos.</p>@endif
</section>
@if (filled($pressRelease->source_text))
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
        <details>
            <summary class="cursor-pointer text-lg font-semibold">Contenido preparado para procesamiento</summary>
            <p class="mt-2 text-xs text-slate-500">Texto normalizado del correo y sus documentos. El contenido se considera no confiable.</p>
            <div class="mt-4 max-h-[32rem] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 font-mono text-xs leading-6 text-slate-700">{{ $pressRelease->source_text }}</div>
        </details>
    </section>
@endif
@if ($pressRelease->generatedArticle)
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Noticia generada</h2>
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Pendiente de validación</span>
        </div>
        <h3 class="mt-5 text-2xl font-bold tracking-tight">{{ $pressRelease->generatedArticle->headline }}</h3>
        @if ($pressRelease->generatedArticle->subheadline)<p class="mt-2 text-lg text-slate-600">{{ $pressRelease->generatedArticle->subheadline }}</p>@endif
        <p class="mt-5 font-semibold leading-7 text-slate-800">{{ $pressRelease->generatedArticle->lead }}</p>
        <div class="mt-5 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">{{ $pressRelease->generatedArticle->body }}</div>
        <dl class="mt-6 grid gap-4 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500">Título SEO</dt><dd class="mt-1 font-medium">{{ $pressRelease->generatedArticle->seo_title }}</dd></div>
            <div><dt class="text-slate-500">Categoría sugerida</dt><dd class="mt-1 font-medium">{{ $pressRelease->generatedArticle->suggested_category ?: 'Sin sugerencia' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">Descripción SEO</dt><dd class="mt-1">{{ $pressRelease->generatedArticle->seo_description }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-slate-500">Etiquetas sugeridas</dt><dd class="mt-1">{{ implode(', ', $pressRelease->generatedArticle->suggested_tags ?? []) ?: 'Sin sugerencias' }}</dd></div>
        </dl>
    </section>
@endif
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
    <h2 class="text-lg font-semibold">Adjuntos ({{ $pressRelease->attachments->count() }})</h2>
    <ul class="mt-4 divide-y divide-slate-100">
        @forelse ($pressRelease->attachments as $attachment)
            <li class="flex flex-wrap items-center justify-between gap-3 py-4"><div class="min-w-0"><p class="break-words text-sm font-medium">{{ $attachment->original_filename }}</p><p class="mt-1 text-xs text-slate-500">{{ $attachment->mime_type }} · {{ number_format($attachment->size / 1024, 1, ',', '.') }} KB</p>@if ($attachment->is_blocked)<p class="mt-2 text-sm font-semibold text-red-700">Bloqueado: {{ $attachment->blocked_reason }}</p>@endif</div>@if ($attachment->is_blocked)<span class="rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-700">Descarga bloqueada</span>@else<a href="{{ route('press-releases.attachments.download', [$pressRelease, $attachment]) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-brand-600 hover:bg-slate-50">Descargar<span class="sr-only"> {{ $attachment->original_filename }}</span></a>@endif</li>
        @empty
            <li class="py-2 text-sm text-slate-500">No hay adjuntos guardados para este correo.</li>
        @endforelse
    </ul>
</section>
@endsection
