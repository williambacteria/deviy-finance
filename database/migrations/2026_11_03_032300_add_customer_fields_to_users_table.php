<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('company')->nullable()->after('phone');
            $table->decimal('balance', 20, 6)->default(0)->after('company');
            $table->decimal('credit_limit', 20, 6)->default(0)->after('balance');
            $table->string('status', 24)->default('active')->after('credit_limit');
            $table->string('real_name_status', 24)->default('unverified')->after('status');
            $table->string('customer_group', 32)->default('default')->after('real_name_status');
            $table->timestamp('last_login_at')->nullable()->after('customer_group');
            $table->text('notes')->nullable()->after('last_login_at');
            $table->json('metadata')->nullable()->after('notes');

            $table->index(['status', 'created_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['phone']);
            $table->dropColumn([
                'phone',
                'company',
                'balance',
                'credit_limit',
                'status',
                'real_name_status',
                'customer_group',
                'last_login_at',
                'notes',
                'metadata',
            ]);
        });
    }
};
