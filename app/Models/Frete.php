<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Frete extends Model
{
    use HasFactory;

    // Libera a gravação em todas as colunas
    protected $guarded = [];

    // Garante que o Vue receba os formatos corretos (verdadeiro/falso)
    protected $casts = [
        'temTde' => 'boolean',
        'is_correto' => 'boolean',
    ];
}