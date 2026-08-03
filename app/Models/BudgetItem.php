<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    use HasFactory;

    protected $table = 'budget_items';

    protected $fillable = [
        'budget_id',
        'categoria',
        'nome',
        'valores',
    ];

    // ESTA É A MÁGICA PARA O BANCO DE DADOS!
    // Transforma o array do PHP em JSON pro MySQL e vice-versa automaticamente.
    protected $casts = [
        'valores' => 'json',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }
}
