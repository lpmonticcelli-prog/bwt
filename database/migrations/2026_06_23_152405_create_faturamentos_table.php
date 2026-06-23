<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturamentos', function (Blueprint $table) {
            $table->id();
            // Aumentando os limites para o MySQL nunca mais reclamar de "Data too long"
            $table->string('arquivo', 255)->unique();
            $table->string('destino', 150);
            $table->string('regra', 100);
            $table->string('tipo_cte', 100);
            $table->string('nfe_chave', 255)->nullable(); 
            $table->string('produto', 255)->nullable(); 
            
            $table->decimal('valor_carga', 12, 2);
            $table->decimal('custo_frete_base', 12, 2);
            $table->decimal('custo_tde', 12, 2);
            $table->decimal('custo_total', 12, 2);
            $table->decimal('receita_frete_base', 12, 2);
            $table->decimal('receita_tde', 12, 2);
            $table->decimal('receita_icms', 12, 2);
            $table->decimal('receita_teorica', 12, 2);
            $table->decimal('receita_real', 12, 2); 
            $table->decimal('lucro', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturamentos');
    }
};