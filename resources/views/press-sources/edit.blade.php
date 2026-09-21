@extends('layouts.app')
@section('content')
<div class="mb-7"><a href="{{ route('press-sources.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a fuentes</a><div class="mt-4 flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-slate-200 font-bold text-slate-600">{{ mb_strtoupper(mb_substr($pressSource->name, 0, 1)) }}</span><div><h1 class="text-3xl font-bold tracking-tight text-slate-950">Editar fuente</h1><p class="mt-1 text-sm text-slate-500">{{ $pressSource->name }}</p></div></div></div>
<form method="POST" action="{{ route('press-sources.update', $pressSource) }}" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-soft sm:p-8">@csrf @method('PUT') @include('press-sources._form')</form>
@endsection
