<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('origin', 16);
            $table->string('headline');
            $table->string('subheadline')->nullable();
            $table->text('lead');
            $table->longText('body');
            $table->string('seo_title');
            $table->text('seo_description');
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['generated_article_id', 'version']);
            $table->index(['generated_article_id', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
