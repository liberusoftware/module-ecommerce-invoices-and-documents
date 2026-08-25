<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('invoicing_burned_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('series_id')->constrained('invoicing_series')->restrictOnDelete();
            $table->string('number');
            $table->unsignedBigInteger('number_sequence');
            $table->string('reason');
            $table->timestamp('burned_at');
            $table->timestamps();

            $table->unique(['series_id', 'number_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoicing_burned_numbers');
    }
};
