<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 20)->default('info')->index(); // error | warning | info | debug
            $table->string('channel', 80)->nullable();             // e.g. "laravel", "go-api"
            $table->text('message');
            $table->longText('stack_trace')->nullable();
            $table->json('context')->nullable();                   // user_id, request URL, env, etc.
            $table->string('source_app', 120)->nullable();         // name of the originating app
            $table->string('environment', 30)->nullable();         // production | staging | local
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_logs');
    }
};
