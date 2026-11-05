<div aria-label="Estado de la IA" class="rounded-xl border border-slate-500/25 p-3 text-xs">
    <div class="flex items-center justify-between gap-2"><span class="font-semibold">OpenAI</span><span class="flex items-center gap-1.5 {{ ['gray' => 'text-slate-400', 'green' => 'text-emerald-400', 'red' => 'text-red-400', 'amber' => 'text-amber-500'][$aiIndicator['tone']] }}"><span aria-hidden="true" class="h-2 w-2 shrink-0 rounded-full {{ ['gray' => 'bg-slate-400', 'green' => 'bg-emerald-400', 'red' => 'bg-red-400', 'amber' => 'bg-amber-500'][$aiIndicator['tone']] }}"></span>{{ $aiIndicator['label'] }}</span></div>
    <p class="mt-2 leading-5 opacity-75">{{ $aiIndicator['detail'] }}</p>
    @if($aiIndicator['checked_at'])<p class="mt-1 opacity-60">Última petición: <time datetime="{{ $aiIndicator['checked_at']->toIso8601String() }}">{{ $aiIndicator['checked_at']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time></p>@endif
    @if($aiIndicator['tone'] === 'red')<a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer" class="mt-2 inline-block underline">Revisar facturación ↗</a>@endif
</div>
