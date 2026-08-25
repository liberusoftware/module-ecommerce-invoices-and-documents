<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_series', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('code');
            $table->string('prefix')->default('');
            $table->unsignedTinyInteger('pad')->default(0);
            $table->unsignedBigInteger('next_value')->default(1);

            // A fiscal series files documents a tax authority may ask for; a
            // proforma may not be filed under one.
            $table->boolean('fiscal')->default(true);

            // Gaplessness is a per-series policy, not a property of invoicing.
            $table->boolean('gapless')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_series');
    }
};
