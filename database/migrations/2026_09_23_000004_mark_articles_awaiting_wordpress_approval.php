<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('generated_articles')
            ->where('validation_risk', 'low')
            ->where('requires_editorial_approval', true)
            ->orderBy('id')
            ->chunkById(500, function ($articles): void {
                DB::table('press_releases')
                    ->whereIn('id', $articles->pluck('press_release_id'))
                    ->where('processing_status', 'needs_review')
                    ->update(['processing_status' => 'awaiting_wordpress_approval']);
            });
    }

    public function down(): void
    {
        DB::table('press_releases')
            ->where('processing_status', 'awaiting_wordpress_approval')
            ->update(['processing_status' => 'needs_review']);
    }
};
