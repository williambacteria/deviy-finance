<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64);
            $table->string('source_table', 96);
            $table->string('source_id', 96);
            $table->string('target_table', 96)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('state', 32)->default('archived');
            $table->longText('payload');
            $table->timestamps();
            $table->unique(['source', 'source_table', 'source_id'], 'legacy_import_source_unique');
            $table->index(['target_table', 'target_id', 'source_table'], 'legacy_import_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_records');
    }
};
