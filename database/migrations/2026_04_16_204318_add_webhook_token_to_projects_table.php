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
        Schema::table('projects', function (Blueprint $table) {
            $table->string('webhook_token', 64)->nullable()->unique()->index()->after('links');
        });

        // Backfill existing projects with a unique token
        \App\Models\Project::whereNull('webhook_token')->each(function ($project) {
            $project->updateQuietly(['webhook_token' => bin2hex(random_bytes(32))]);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('webhook_token');
        });
    }
};
