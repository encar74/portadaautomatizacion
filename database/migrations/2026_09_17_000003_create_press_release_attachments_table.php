<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_release_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_release_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('mime_type');
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('attachment_type', 24);
            $table->string('storage_path');
            $table->longText('extracted_text')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('wordpress_media_id')->nullable();
            $table->timestamps();

            $table->unique(['press_release_id', 'stored_filename'], 'attachments_release_filename_unique');
            $table->index('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_release_attachments');
    }
};
