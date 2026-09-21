<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('press_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id')->unique();
            $table->string('sender_email');
            $table->string('sender_name')->nullable();
            $table->string('subject');
            $table->dateTime('received_at');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->string('classification')->nullable();
            $table->string('processing_status', 40);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['processing_status', 'received_at']);
            $table->index('sender_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_releases');
    }
};
