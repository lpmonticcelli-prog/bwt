<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            if (!Schema::hasColumn('faturamentos', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('regra');
            }
        });
        
        Schema::table('fretes', function (Blueprint $table) {
            if (!Schema::hasColumn('fretes', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('regra');
            }
        });
    }

    public function down(): void
    {
        Schema::table('faturamentos', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
        Schema::table('fretes', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
    }
};