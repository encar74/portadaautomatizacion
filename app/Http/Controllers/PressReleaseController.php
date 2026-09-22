<?php

namespace App\Http\Controllers;

use App\Enums\PressReleaseStatus;
use App\Models\PressRelease;
use App\Models\PressReleaseAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PressReleaseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(PressReleaseStatus::class)],
        ]);
        $query = PressRelease::query()->with('pressSource')->withCount('attachments');
        if ($search = trim($filters['q'] ?? '')) {
            $query->where(function ($query) use ($search) {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhere('sender_email', 'like', "%{$search}%")
                    ->orWhere('sender_name', 'like', "%{$search}%");
            });
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('processing_status', $status);
        }

        return view('press-releases.index', [
            'releases' => $query->orderByDesc('received_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'statuses' => PressReleaseStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(PressRelease $pressRelease): View
    {
        $pressRelease->load(['pressSource', 'attachments', 'generatedArticle.versions']);
        $body = $pressRelease->body_text;
        if (! filled($body) && filled($pressRelease->body_html)) {
            $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', $pressRelease->body_html);
            $html = preg_replace('~<br\s*/?>|</(?:p|div|tr|li|h[1-6])\s*>~i', "\n", $html);
            $body = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return view('press-releases.show', compact('pressRelease', 'body'));
    }

    public function download(PressRelease $pressRelease, PressReleaseAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->press_release_id === $pressRelease->id, 404);
        abort_if($attachment->is_blocked, 423, 'Este adjunto está bloqueado por seguridad.');
        $disk = Storage::disk(config('press_releases.disk'));
        abort_unless($disk->exists($attachment->storage_path), 404);

        return $disk->download($attachment->storage_path, $attachment->original_filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
