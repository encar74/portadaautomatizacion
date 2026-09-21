<?php

namespace App\Http\Controllers;

use App\Http\Requests\PressSourceRequest;
use App\Models\PressSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PressSourceController extends Controller
{
    public function index(): View
    {
        return view('press-sources.index', [
            'sources' => PressSource::query()->orderByDesc('priority')->orderBy('name')->paginate(20),
            'stats' => [
                'total' => PressSource::query()->count(),
                'active' => PressSource::query()->where('is_active', true)->count(),
                'automatic' => PressSource::query()->where('is_active', true)->where('processing_mode', 'automatic')->count(),
                'review' => PressSource::query()->where('is_active', true)->where('processing_mode', 'review')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('press-sources.create');
    }

    public function store(PressSourceRequest $request): RedirectResponse
    {
        PressSource::create($request->validated());

        return redirect()->route('press-sources.index')->with('status', 'Fuente creada correctamente.');
    }

    public function edit(PressSource $pressSource): View
    {
        return view('press-sources.edit', compact('pressSource'));
    }

    public function update(PressSourceRequest $request, PressSource $pressSource): RedirectResponse
    {
        $pressSource->update($request->validated());

        return redirect()->route('press-sources.index')->with('status', 'Fuente actualizada correctamente.');
    }

    public function destroy(PressSource $pressSource): RedirectResponse
    {
        $pressSource->delete();

        return redirect()->route('press-sources.index')->with('status', 'Fuente eliminada correctamente.');
    }
}
