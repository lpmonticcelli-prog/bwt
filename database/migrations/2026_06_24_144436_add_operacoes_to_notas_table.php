<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adiciona as colunas na tabela da E4LOG
        Schema::table('fretes', function (Blueprint $table) {
            $table->string('tipo_operacao')->default('Entrega');
            $table->date('data_emissao')->nullable();
            $table->date('data_entrega')->nullable();
        });

        // Adiciona as colunas na tabela da Sol Fácil
        Schema::table('faturamentos', function (Blueprint $table) {
            $table->string('tipo_operacao')->default('Entrega');
            $table->date('data_emissao')->nullable();
            $table->date('data_entrega')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fretes', function (Blueprint $table) {
            $table->dropColumn(['tipo_operacao', 'data_emissao', 'data_entrega']);
        });

        Schema::table('faturamentos', function (Blueprint $table) {
            $table->dropColumn(['tipo_operacao', 'data_emissao', 'data_entrega']);
        });
    }
};