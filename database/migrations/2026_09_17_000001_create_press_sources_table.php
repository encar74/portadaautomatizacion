<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('domain')->nullable();
            $table->string('match_type', 32);
            $table->boolean('is_active')->default(true);
            $table->string('processing_mode', 32);
            $table->string('default_category')->nullable();
            $table->json('default_tags')->nullable();
            $table->integer('priority')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'match_type', 'email'], 'press_sources_email_match_idx');
            $table->index(['is_active', 'match_type', 'domain'], 'press_sources_domain_match_idx');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_sources');
    }
};
