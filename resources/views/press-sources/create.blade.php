@extends('layouts.app')
@section('content')
<div class="mb-7"><a href="{{ route('press-sources.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a fuentes</a><h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950">Nueva fuente</h1><p class="mt-2 text-sm text-slate-500">Autoriza un remitente y configura su comportamiento editorial.</p></div>
<form method="POST" action="{{ route('press-sources.store') }}" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft sm:p-8">@csrf @include('press-sources._form')</form>
@endsection
