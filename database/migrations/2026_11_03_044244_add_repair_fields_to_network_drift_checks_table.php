<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_drift_checks', function (Blueprint $table) {
            $table->string('action', 20)->default('none')->after('status');
            $table->string('repair_status', 20)->nullable()->after('action');
            $table->json('repair_result')->nullable()->after('repair_status');
            $table->timestamp('repaired_at')->nullable()->after('repair_result');
            $table->timestamp('notified_at')->nullable()->after('repaired_at');
        });
    }

    public function down(): void
    {
        Schema::table('network_drift_checks', function (Blueprint $table) {
            $table->dropColumn(['action', 'repair_status', 'repair_result', 'repaired_at', 'notified_at']);
        });
    }
};
