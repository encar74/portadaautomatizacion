<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->boolean('requires_editorial_approval')->default(false)->after('repaired_at');
        });

        Schema::table('article_versions', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('origin')->constrained('users')->nullOnDelete();
            $table->text('editorial_instruction')->nullable()->after('prompt_version');
        });

        Schema::create('article_editorial_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('status', 24)->default('completed');
            $table->string('risk', 16)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['generated_article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_editorial_actions');
        Schema::table('article_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('editorial_instruction');
        });
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn('requires_editorial_approval');
        });
    }
};
