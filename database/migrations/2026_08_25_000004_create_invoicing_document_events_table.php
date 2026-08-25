<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_document_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained('invoicing_documents')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('kind');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->string('actor_ref')->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Arbitrates a concurrent append. The model's guard is what stops an
            // existing row being edited; an index does not.
            $table->unique(['document_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_document_events');
    }
};
