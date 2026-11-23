<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('real_name_type', 24)->default('personal')->after('real_name_status');
            $table->string('real_name_company', 180)->nullable()->after('real_name');
            $table->string('real_name_credit_code', 32)->nullable()->after('real_name_company');
            $table->text('real_name_legal_name')->nullable()->after('real_name_credit_code');
            $table->text('real_name_legal_phone')->nullable()->after('real_name_legal_name');
            $table->text('real_name_license')->nullable()->after('real_name_legal_phone');
            $table->text('real_name_legal_id')->nullable()->after('real_name_license');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'real_name_type',
                'real_name_company',
                'real_name_credit_code',
                'real_name_legal_name',
                'real_name_legal_phone',
                'real_name_license',
                'real_name_legal_id',
            ]);
        });
    }
};
