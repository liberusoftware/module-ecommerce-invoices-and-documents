<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained('invoicing_documents')->restrictOnDelete();

            // The caller's reference for this attempt: a retry cannot send twice.
            $table->string('reference');

            $table->string('channel');
            $table->string('address')->nullable();
            $table->string('state');
            $table->text('detail')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('redacted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['document_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_deliveries');
    }
};
