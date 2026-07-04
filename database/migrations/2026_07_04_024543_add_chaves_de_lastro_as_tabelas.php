<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            if (!Schema::hasColumn('faturamentos', 'cte_chave')) {
                $table->string('cte_chave', 100)->nullable()->after('arquivo');
                $table->string('chave_complementada', 100)->nullable()->after('cte_chave');
            }
        });
        
        Schema::table('fretes', function (Blueprint $table) {
            if (!Schema::hasColumn('fretes', 'cte_chave')) {
                $table->string('cte_chave', 100)->nullable()->after('arquivo');
                $table->string('chave_complementada', 100)->nullable()->after('cte_chave');
            }
        });
    }

    public function down(): void
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            $table->dropColumn(['cte_chave', 'chave_complementada']);
        });
        Schema::table('fretes', function (Blueprint $table) {
            $table->dropColumn(['cte_chave', 'chave_complementada']);
        });
    }
};