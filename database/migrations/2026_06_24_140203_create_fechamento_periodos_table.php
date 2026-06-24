<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cria a gaveta principal dos Fechamentos
        Schema::create('fechamento_periodos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo'); // Ex: Janeiro - 1ª Quinzena
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->date('data_vencimento'); // Receberá a regra de +30 dias
            $table->string('status')->default('aberto'); // Aberto para receber XMLs, Fechado após auditoria
            $table->timestamps();
        });

        // 2. Conecta a tabela de Fretes (E4LOG) a este fechamento
        Schema::table('fretes', function (Blueprint $table) {
            $table->foreignId('fechamento_periodo_id')->nullable()->constrained('fechamento_periodos')->nullOnDelete();
        });

        // 3. Conecta a tabela de Faturamento (BWT/Sol Fácil) a este fechamento
        Schema::table('faturamentos', function (Blueprint $table) {
            $table->foreignId('fechamento_periodo_id')->nullable()->constrained('fechamento_periodos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            $table->dropForeign(['fechamento_periodo_id']);
            $table->dropColumn('fechamento_periodo_id');
        });

        Schema::table('fretes', function (Blueprint $table) {
            $table->dropForeign(['fechamento_periodo_id']);
            $table->dropColumn('fechamento_periodo_id');
        });

        Schema::dropIfExists('fechamento_periodos');
    }
};