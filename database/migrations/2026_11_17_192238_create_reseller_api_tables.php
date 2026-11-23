<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reseller_accounts', function (Blueprint $t): void {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('status',20)->default('pending')->index(); $t->string('site_name',100); $t->string('site_url',255);
            $t->text('purpose'); $t->json('ip_allowlist')->nullable(); $t->string('secret_hash',64)->nullable();
            $t->string('secret_hint',16)->nullable(); $t->text('review_note')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
        });
        Schema::create('reseller_tokens', function (Blueprint $t): void {
            $t->id(); $t->foreignId('reseller_account_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash',64)->unique(); $t->timestamp('expires_at')->index(); $t->timestamps();
        });
        Schema::create('reseller_cart_items', function (Blueprint $t): void {
            $t->id(); $t->foreignId('reseller_account_id')->constrained()->cascadeOnDelete();
            $t->string('request_key',64); $t->string('payload_hash',64); $t->json('payload');
            $t->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestamp('expires_at'); $t->timestamps(); $t->unique(['reseller_account_id','request_key']);
        });
        Schema::create('reseller_api_logs', function (Blueprint $t): void {
            $t->id(); $t->foreignId('reseller_account_id')->constrained()->cascadeOnDelete();
            $t->string('method',10); $t->string('path',190); $t->string('ip',45); $t->unsignedSmallInteger('status');
            $t->string('request_id',36); $t->timestamp('created_at'); $t->index(['reseller_account_id','id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('reseller_api_logs'); Schema::dropIfExists('reseller_cart_items');
        Schema::dropIfExists('reseller_tokens'); Schema::dropIfExists('reseller_accounts');
    }
};
