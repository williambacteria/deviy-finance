<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ops_alerts', function (Blueprint $table) {
            $table->timestamp('escalated_at')->nullable()->after('acknowledged_at');
            $table->timestamp('notified_at')->nullable()->after('escalated_at');
        });
    }

    public function down(): void
    {
        Schema::table('ops_alerts', function (Blueprint $table) {
            $table->dropColumn(['escalated_at', 'notified_at']);
        });
    }
};
