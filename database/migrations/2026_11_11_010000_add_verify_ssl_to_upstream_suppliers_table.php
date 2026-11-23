<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upstream_suppliers', function (Blueprint $table) {
            $table->boolean('verify_ssl')->default(true)->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('upstream_suppliers', function (Blueprint $table) {
            $table->dropColumn('verify_ssl');
        });
    }
};
