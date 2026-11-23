<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->json('diagnostics')->nullable()->after('cert_expires_at');
            $table->timestamp('diagnosed_at')->nullable()->after('diagnostics');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->dropColumn(['diagnostics', 'diagnosed_at']);
        });
    }
};
