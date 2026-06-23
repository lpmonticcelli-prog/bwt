<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->decimal('fixed_value', 10, 2); // Ex: 200.00, 350.00
            $table->decimal('excess_percentage', 5, 2); // Ex: 2.00, 3.00
            $table->decimal('tde_min_value', 10, 2); // Ex: 200.00
            $table->decimal('tde_percentage', 5, 2); // Ex: 20.00, 30.00
            $table->decimal('icms_percentage', 5, 2)->default(0.00); // Ex: 12.00 para a BWT
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};