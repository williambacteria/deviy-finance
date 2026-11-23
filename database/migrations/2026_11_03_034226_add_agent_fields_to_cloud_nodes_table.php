<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->string('agent_node_id', 80)->nullable()->unique()->after('name');
            $table->string('agent_url')->nullable()->after('agent_node_id');
            $table->text('agent_token')->nullable()->after('agent_url');
            $table->string('agent_mode', 24)->default('mock')->after('agent_token');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_nodes', function (Blueprint $table) {
            $table->dropUnique(['agent_node_id']);
            $table->dropColumn(['agent_node_id', 'agent_url', 'agent_token', 'agent_mode']);
        });
    }
};
