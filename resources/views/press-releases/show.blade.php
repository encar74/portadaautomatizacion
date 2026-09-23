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
    <section id="articulo" class="mt-6 scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-soft">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Noticia generada</h2>
            @if ($pressRelease->generatedArticle->validation_risk)
                <span @class([
                    'rounded-full px-3 py-1 text-xs font-semibold',
                    'bg-emerald-50 text-emerald-800' => $pressRelease->generatedArticle->validation_risk->value === 'low',
                    'bg-amber-50 text-amber-800' => $pressRelease->generatedArticle->validation_risk->value === 'medium',
                    'bg-red-50 text-red-800' => $pressRelease->generatedArticle->validation_risk->value === 'high',
                ])>{{ $pressRelease->generatedArticle->validation_risk->label() }}</span>
            @else
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Pendiente de validación</span>
            @endif
        </div>
        @php($hadElevatedRisk = $pressRelease->generatedArticle->validationAttempts->contains(fn ($attempt) => in_array($attempt->risk->value, ['medium', 'high'], true)))
        @if ($hadElevatedRisk && $pressRelease->generatedArticle->repair_status === 'resolved_low')
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-semibold">Reparado automáticamente después de una validación de riesgo medio o alto</p>
                <p class="mt-1">El riesgo actual es bajo, pero este artículo fue modificado por la IA antes de enviarse a WordPress. Conviene revisar el historial inferior.</p>
            </div>
        @elseif ($hadElevatedRisk && in_array($pressRelease->generatedArticle->repair_status, ['requires_review', 'failed'], true) && in_array($pressRelease->generatedArticle->validation_risk?->value, ['medium', 'high'], true))
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <p class="font-semibold">La reparación automática no resolvió el riesgo</p>
                <p class="mt-1">El artículo permanece bloqueado para revisión humana y no se enviará automáticamente a WordPress.</p>
            </div>
        @elseif ($hadElevatedRisk && in_array($pressRelease->generatedArticle->repair_status, ['requires_review', 'failed'], true) && $pressRelease->generatedArticle->validation_risk?->value === 'low')
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-semibold">Riesgo resuelto mediante intervención editorial</p>
                <p class="mt-1">La reparación automática no fue suficiente, pero la versión corregida posteriormente ha obtenido riesgo bajo. Requiere aprobación humana antes de WordPress.</p>
            </div>
        @elseif (in_array($pressRelease->generatedArticle->repair_status, ['queued', 'running', 'awaiting_validation'], true))
            <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">La reparación automática está en curso y todavía no puede enviarse a WordPress.</div>
        @endif
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
        @if ($pressRelease->generatedArticle->validation_issues)
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4">
                <h4 class="font-semibold text-red-900">Incidencias factuales</h4>
                <ul class="mt-3 space-y-3 text-sm text-red-900">
                    @foreach ($pressRelease->generatedArticle->validation_issues as $issue)
                        <li><span class="font-semibold">{{ $issue['claim'] }}</span><br>{{ $issue['explanation'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($pressRelease->generatedArticle->warnings)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h4 class="font-semibold text-amber-900">Advertencias editoriales</h4>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900">
                    @foreach ($pressRelease->generatedArticle->warnings as $warning)<li>{{ $warning }}</li>@endforeach
                </ul>
            </div>
        @endif

        @php($editorialBusy = in_array($pressRelease->generatedArticle->repair_status, ['queued', 'running', 'awaiting_validation'], true) || $pressRelease->generatedArticle->editorialActions->contains('status', 'pending'))
        @if (! $pressRelease->generatedArticle->wordpressPublication && ! $editorialBusy)
            <div class="mt-8 border-t border-slate-200 pt-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><h4 class="font-semibold">Revisión editorial</h4><p class="mt-1 text-sm text-slate-500">Toda corrección crea una versión nueva y vuelve a pasar la validación factual.</p></div>
                </div>

                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                    <details class="rounded-xl border border-slate-200 bg-slate-50 p-4" @if($errors->hasAny(['headline', 'lead', 'body', 'seo_title', 'seo_description'])) open @endif>
                        <summary class="cursor-pointer font-semibold text-brand-600">Editar manualmente</summary>
                        <form method="POST" action="{{ route('generated-articles.update', $pressRelease->generatedArticle) }}" class="mt-4 space-y-4">
                            @csrf @method('PUT')
                            <div><label for="headline" class="mb-1 block text-sm font-medium">Titular</label><input id="headline" name="headline" value="{{ old('headline', $pressRelease->generatedArticle->headline) }}" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
                            <div><label for="subheadline" class="mb-1 block text-sm font-medium">Subtítulo</label><input id="subheadline" name="subheadline" value="{{ old('subheadline', $pressRelease->generatedArticle->subheadline) }}" maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
                            <div><label for="lead" class="mb-1 block text-sm font-medium">Entradilla</label><textarea id="lead" name="lead" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('lead', $pressRelease->generatedArticle->lead) }}</textarea></div>
                            <div><label for="body" class="mb-1 block text-sm font-medium">Cuerpo</label><textarea id="body" name="body" rows="12" required class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('body', $pressRelease->generatedArticle->body) }}</textarea></div>
                            <div><label for="seo_title" class="mb-1 block text-sm font-medium">Título SEO</label><input id="seo_title" name="seo_title" value="{{ old('seo_title', $pressRelease->generatedArticle->seo_title) }}" required maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div>
                            <div><label for="seo_description" class="mb-1 block text-sm font-medium">Descripción SEO</label><textarea id="seo_description" name="seo_description" rows="3" required class="w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('seo_description', $pressRelease->generatedArticle->seo_description) }}</textarea></div>
                            <div class="grid gap-4 sm:grid-cols-2"><div><label for="suggested_category" class="mb-1 block text-sm font-medium">Categoría</label><input id="suggested_category" name="suggested_category" value="{{ old('suggested_category', $pressRelease->generatedArticle->suggested_category) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div><div><label for="suggested_tags" class="mb-1 block text-sm font-medium">Etiquetas separadas por comas</label><input id="suggested_tags" name="suggested_tags" value="{{ old('suggested_tags', implode(', ', $pressRelease->generatedArticle->suggested_tags ?? [])) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2"></div></div>
                            <div><label for="editorial_note" class="mb-1 block text-sm font-medium">Nota del cambio</label><textarea id="editorial_note" name="editorial_note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Qué has corregido y por qué">{{ old('editorial_note') }}</textarea></div>
                            <button class="rounded-lg bg-slate-950 px-5 py-2 text-sm font-semibold text-white">Guardar y volver a validar</button>
                        </form>
                    </details>

                    <div class="space-y-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h5 class="font-semibold">Pedir una corrección a la IA</h5>
                            <p class="mt-1 text-sm text-slate-500">Describe exactamente qué debe corregir. La instrucción y la respuesta quedarán registradas.</p>
                            <form method="POST" action="{{ route('generated-articles.ai-correction', $pressRelease->generatedArticle) }}" class="mt-4">
                                @csrf
                                <label for="instruction" class="sr-only">Instrucciones para la IA</label>
                                <textarea id="instruction" name="instruction" rows="5" required minlength="10" maxlength="4000" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Ejemplo: elimina la cifra de asistentes porque no aparece en la nota y conserva el resto del artículo.">{{ old('instruction') }}</textarea>
                                <button class="mt-3 rounded-lg border border-brand-600 px-5 py-2 text-sm font-semibold text-brand-600 hover:bg-brand-50">Generar nueva versión con IA</button>
                            </form>
                        </div>

                        @if (config('wordpress.enabled') && in_array($pressRelease->generatedArticle->validation_risk?->value, ['low', 'medium'], true))
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <h5 class="font-semibold text-emerald-950">Aprobación editorial</h5>
                                <p class="mt-1 text-sm text-emerald-900">El borrador solo se creará cuando confirmes esta versión.</p>
                                <form method="POST" action="{{ route('generated-articles.wordpress-draft', $pressRelease->generatedArticle) }}" class="mt-4">
                                    @csrf
                                    <label for="justification" class="mb-1 block text-sm font-medium text-emerald-950">{{ $pressRelease->generatedArticle->validation_risk?->value === 'medium' ? 'Justificación obligatoria para aceptar el riesgo medio' : 'Nota de aprobación (opcional)' }}</label>
                                    <textarea id="justification" name="justification" rows="3" @required($pressRelease->generatedArticle->validation_risk?->value === 'medium') class="w-full rounded-lg border border-emerald-300 bg-white px-3 py-2" placeholder="Indica qué has comprobado antes de aprobar.">{{ old('justification') }}</textarea>
                                    <button class="mt-3 rounded-lg bg-emerald-700 px-5 py-2 text-sm font-semibold text-white">Crear borrador en WordPress</button>
                                </form>
                            </div>
                        @elseif (! config('wordpress.enabled'))
                            <div class="rounded-xl border border-slate-200 p-4 text-sm text-slate-600">La conexión con WordPress está desactivada. Puedes corregir y validar el artículo, pero no crear todavía el borrador.</div>
                        @endif
                    </div>
                </div>
            </div>
        @elseif (! $pressRelease->generatedArticle->wordpressPublication && $editorialBusy)
            <div class="mt-8 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"><span class="font-semibold">Hay una acción editorial en curso.</span> Las opciones de edición volverán a estar disponibles cuando termine la reparación, corrección o publicación pendiente.</div>
        @endif

        @if ($pressRelease->generatedArticle->wordpressPublication)
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                <div><p class="font-semibold">Borrador creado en WordPress</p><p class="mt-1">El artículo aún no está publicado.</p></div>
                @if ($pressRelease->generatedArticle->wordpressPublication->wordpress_edit_url)<a href="{{ $pressRelease->generatedArticle->wordpressPublication->wordpress_edit_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold underline">Abrir en WordPress ↗</a>@endif
            </div>
        @endif

        @if ($pressRelease->generatedArticle->validationAttempts->isNotEmpty())
            <div class="mt-8 border-t border-slate-200 pt-6">
                <h4 class="font-semibold">Historial de validación y reparación</h4>
                <p class="mt-1 text-sm text-slate-500">Este registro se conserva aunque el riesgo actual cambie.</p>
                <ol class="mt-4 space-y-4">
                    @foreach ($pressRelease->generatedArticle->validationAttempts as $attempt)
                        <li class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold">Validación {{ $attempt->sequence }} · {{ $attempt->context === 'after_repair' ? 'después de la reparación' : 'artículo inicial' }}</p>
                                <span @class([
                                    'rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-emerald-50 text-emerald-800' => $attempt->risk->value === 'low',
                                    'bg-amber-50 text-amber-800' => $attempt->risk->value === 'medium',
                                    'bg-red-50 text-red-800' => $attempt->risk->value === 'high',
                                ])>{{ $attempt->risk->label() }}</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">{{ $attempt->articleVersion?->origin->label() ?? 'Versión no disponible' }} · {{ $attempt->created_at?->format('d/m/Y H:i') }}</p>
                            @if ($attempt->issues)
                                <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                    @foreach ($attempt->issues as $issue)<li><span class="font-semibold">{{ $issue['claim'] ?? 'Incidencia' }}</span>: {{ $issue['explanation'] ?? '' }}</li>@endforeach
                                </ul>
                            @else
                                <p class="mt-3 text-sm text-slate-600">No se detectaron incidencias factuales.</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($pressRelease->generatedArticle->versions->isNotEmpty())
            <div class="mt-8 border-t border-slate-200 pt-6">
                <h4 class="font-semibold">Versiones del artículo</h4>
                <ol class="mt-4 space-y-3">
                    @foreach ($pressRelease->generatedArticle->versions->sortByDesc('version') as $version)
                        <li class="rounded-xl bg-slate-50 p-4 text-sm"><span class="font-semibold">Versión {{ $version->version }} · {{ $version->origin->label() }}</span><span class="text-slate-500"> · {{ $version->author?->name ?? 'Sistema' }} · {{ $version->created_at?->format('d/m/Y H:i') }}</span>@if($version->editorial_instruction)<p class="mt-2 text-slate-700">{{ $version->editorial_instruction }}</p>@endif</li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($pressRelease->generatedArticle->editorialActions->isNotEmpty())
            <div class="mt-8 border-t border-slate-200 pt-6">
                <h4 class="font-semibold">Decisiones editoriales</h4>
                <ol class="mt-4 space-y-3">
                    @foreach ($pressRelease->generatedArticle->editorialActions->sortByDesc('created_at') as $action)
                        @php($actionLabel = match($action->type) {
                            'manual_edit' => 'Edición manual',
                            'ai_correction_requested' => 'Corrección solicitada a la IA',
                            'medium_risk_override' => 'Aprobación excepcional con riesgo medio',
                            'wordpress_draft_approved' => 'Aprobación para WordPress',
                            'automatic_wordpress_draft' => 'Envío automático a WordPress',
                            default => $action->type,
                        })
                        <li class="rounded-xl bg-slate-50 p-4 text-sm">
                            <div class="flex flex-wrap justify-between gap-2"><span class="font-semibold">{{ $actionLabel }}</span><span class="text-slate-500">{{ $action->user?->name ?? 'Sistema' }} · {{ $action->created_at?->format('d/m/Y H:i') }}</span></div>
                            <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">Estado: {{ match($action->status) { 'pending' => 'pendiente', 'completed' => 'completada', 'failed' => 'fallida', default => $action->status } }}@if($action->risk) · {{ $action->risk->label() }}@endif</p>
                            @if($action->notes)<p class="mt-2 text-slate-700">{{ $action->notes }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
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
