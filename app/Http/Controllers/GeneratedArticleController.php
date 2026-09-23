<?php

namespace App\Http\Controllers;

use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeneratedArticleController extends Controller
{
    public function index(Request $request): View
    {
        $riskValues = array_map(fn (ValidationRisk $risk) => $risk->value, ValidationRisk::cases());
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'risk' => ['nullable', Rule::in([...$riskValues, 'pending'])],
            'status' => ['nullable', Rule::enum(PressReleaseStatus::class)],
        ]);

        $query = GeneratedArticle::query()->with('pressRelease.pressSource');

        if ($search = trim($filters['q'] ?? '')) {
            $query->where(function ($query) use ($search) {
                $query->where('headline', 'like', "%{$search}%")
                    ->orWhere('lead', 'like', "%{$search}%")
                    ->orWhereHas('pressRelease', function ($query) use ($search) {
                        $query->where('subject', 'like', "%{$search}%")
                            ->orWhere('sender_email', 'like', "%{$search}%")
                            ->orWhere('sender_name', 'like', "%{$search}%");
                    });
            });
        }

        if (($filters['risk'] ?? null) === 'pending') {
            $query->whereNull('validation_risk');
        } elseif ($risk = $filters['risk'] ?? null) {
            $query->where('validation_risk', $risk);
        }

        if ($status = $filters['status'] ?? null) {
            $query->whereHas('pressRelease', fn ($query) => $query->where('processing_status', $status));
        }

        return view('generated-articles.index', [
            'articles' => $query->orderByDesc('generated_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'risks' => ValidationRisk::cases(),
            'statuses' => PressReleaseStatus::cases(),
            'filters' => $filters,
        ]);
    }
}
