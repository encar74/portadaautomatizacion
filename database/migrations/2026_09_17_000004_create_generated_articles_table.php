<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_release_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline');
            $table->string('subheadline')->nullable();
            $table->text('lead');
            $table->longText('body');
            $table->string('seo_title');
            $table->text('seo_description');
            $table->string('suggested_category')->nullable();
            $table->json('suggested_tags')->nullable();
            $table->string('location')->nullable();
            $table->json('warnings')->nullable();
            $table->string('validation_risk', 16);
            $table->json('validation_issues')->nullable();
            $table->dateTime('generated_at');
            $table->timestamps();

            $table->index('validation_risk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_articles');
    }
};
