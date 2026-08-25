<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_document_lines', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            // restrictOnDelete, not cascade: the host's cascade from the product
            // table deleted lines out from under a header total still printing.
            $table->foreignId('document_id')->constrained('invoicing_documents')->restrictOnDelete();

            $table->unsignedInteger('position');
            $table->string('description');
            $table->bigInteger('quantity_milli');
            $table->bigInteger('unit_net_minor');
            $table->bigInteger('net_minor');
            $table->unsignedInteger('tax_rate_bp');
            $table->bigInteger('tax_minor');
            $table->bigInteger('gross_minor');
            $table->timestamps();

            $table->unique(['document_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_document_lines');
    }
};
