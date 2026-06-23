<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fretes', function (Blueprint $table) {
            $table->id();
            $table->string('arquivo')->unique(); // O 'unique' impede que você salve a mesma nota duas vezes!
            $table->string('destino');
            $table->decimal('valorNF', 12, 2);
            $table->decimal('fixoRegra', 12, 2);
            $table->decimal('percentualRegra', 5, 2);
            $table->decimal('adValoremCalculado', 12, 2);
            $table->decimal('freteBaseCalculado', 12, 2);
            $table->decimal('taxasExtras', 12, 2);
            $table->boolean('temTde');
            $table->decimal('tdeCalculado', 12, 2);
            $table->decimal('cobrado', 12, 2);
            $table->decimal('correto', 12, 2);
            $table->decimal('diferenca', 12, 2);
            $table->boolean('is_correto');
            $table->string('regra');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fretes');
    }
};