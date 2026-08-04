<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssw_despesas', function (Blueprint $table) {
            $table->id();
            $table->string('lancamento')->unique(); // O ID único da despesa na SSW
            $table->date('inclusao')->nullable();
            $table->string('filial')->nullable();
            $table->string('evento')->nullable();
            $table->string('historico')->nullable();
            $table->string('fornecedor')->nullable();
            $table->string('nota_fiscal')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->date('vencimento')->nullable();
            $table->date('pagamento')->nullable();
            $table->string('competencia')->nullable(); // Ex: "07/26"
            $table->string('situacao')->nullable(); // Ex: "Liquidado"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssw_despesas');
    }
};