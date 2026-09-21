<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('operation', 48);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 24);
            $table->text('error_message')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['press_release_id', 'operation']);
            $table->index(['generated_article_id', 'operation']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_executions');
    }
};
