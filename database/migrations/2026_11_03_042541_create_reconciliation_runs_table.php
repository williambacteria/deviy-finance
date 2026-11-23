<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date')->unique();
            $table->unsignedInteger('checked')->default(0);
            $table->unsignedInteger('issues_count')->default(0);
            $table->string('status', 20)->default('passed')->index();
            $table->json('issues')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
