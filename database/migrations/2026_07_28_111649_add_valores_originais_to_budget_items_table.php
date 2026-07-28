<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('budget_items', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_items', 'valores_originais')) {
                $table->json('valores_originais')->nullable()->after('valores_mensais');
            }
        });
    }

    public function down()
    {
        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropColumn('valores_originais');
        });
    }
};