<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_validation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_version_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('context', 24);
            $table->string('risk', 16);
            $table->json('issues')->nullable();
            $table->json('warnings')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->string('prompt_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['generated_article_id', 'sequence']);
            $table->index(['generated_article_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_validation_attempts');
    }
};
