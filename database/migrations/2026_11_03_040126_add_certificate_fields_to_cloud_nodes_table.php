<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->string('cert_fingerprint', 128)->nullable()->after('agent_mode');
            $table->timestamp('cert_expires_at')->nullable()->after('cert_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->dropColumn(['cert_fingerprint', 'cert_expires_at']);
        });
    }
};
