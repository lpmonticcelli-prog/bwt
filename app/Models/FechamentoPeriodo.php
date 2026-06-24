<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FechamentoPeriodo extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'data_inicio',
        'data_fim',
        'data_vencimento',
        'status'
    ];

    // Um Fechamento possui vários fretes da E4LOG
    public function fretes()
    {
        return $this->hasMany(Frete::class);
    }

    // Um Fechamento possui vários faturamentos da BWT
    public function faturamentos()
    {
        return $this->hasMany(Faturamento::class);
    }
}