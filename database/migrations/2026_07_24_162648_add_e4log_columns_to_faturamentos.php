<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            // Cria a coluna e4log_faturado se ela não existir
            if (!Schema::hasColumn('faturamentos', 'e4log_faturado')) {
                $table->boolean('e4log_faturado')->default(false);
            }
            
            // Cria a coluna custo_e4log se ela não existir
            if (!Schema::hasColumn('faturamentos', 'custo_e4log')) {
                $table->decimal('custo_e4log', 12, 2)->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            $table->dropColumn(['e4log_faturado', 'custo_e4log']);
        });
    }
};