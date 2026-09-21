<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wordpress_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_article_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('wordpress_post_id')->unique();
            $table->text('wordpress_url')->nullable();
            $table->text('wordpress_edit_url')->nullable();
            $table->string('wordpress_status', 32)->default('draft');
            $table->timestamps();
            $table->dateTime('last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wordpress_publications');
    }
};
