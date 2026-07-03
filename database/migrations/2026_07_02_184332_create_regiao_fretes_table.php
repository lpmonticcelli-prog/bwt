<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regiao_fretes', function (Blueprint $table) {
            $table->id();
            $table->string('cidade')->unique();
            $table->string('uf', 2)->default('SP');
            $table->integer('regiao_e4log')->nullable(); 
            $table->integer('regiao_solfacil')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regiao_fretes');
    }
};