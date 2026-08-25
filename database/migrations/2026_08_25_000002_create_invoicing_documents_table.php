<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            // The customer-facing handle. The host showed people the primary
            // key, which enumerates every document on the deployment.
            $table->string('reference')->unique();

            $table->string('kind');
            $table->string('state');

            // The cause, opaque: an order, a payment or a refund reference that
            // this module never resolves.
            $table->string('source_ref');

            $table->foreignId('series_id')->nullable()->constrained('invoicing_series')->restrictOnDelete();
            $table->string('number')->nullable();
            $table->unsignedBigInteger('number_sequence')->nullable();

            $table->char('currency', 3);
            $table->unsignedTinyInteger('currency_exponent');

            $table->foreignId('corrects_document_id')->nullable()->constrained('invoicing_documents')->restrictOnDelete();

            $table->string('seller_ref');
            $table->string('seller_name');
            $table->text('seller_address');
            $table->string('seller_tax_id')->nullable();

            $table->string('buyer_ref');
            $table->string('buyer_name');
            $table->text('buyer_address');
            $table->string('buyer_tax_id')->nullable();
            $table->string('buyer_email')->nullable();

            $table->text('note')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamp('retain_until')->nullable();
            $table->timestamp('redacted_at')->nullable();
            $table->timestamps();

            // The cause is the natural key. It exists before this module does,
            // so there is nothing to mint and nothing for a client to hold.
            $table->unique(['tenant_id', 'kind', 'source_ref']);

            // A number is spent once per series.
            $table->unique(['series_id', 'number_sequence']);

            $table->index(['tenant_id', 'state']);
            $table->index(['tenant_id', 'kind', 'issued_at']);
            $table->index('buyer_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_documents');
    }
};
