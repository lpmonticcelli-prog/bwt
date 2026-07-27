<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Um Budget tem vários Itens (as linhas da planilha)
    public function items()
    {
        return $this->hasMany(BudgetItem::class);
    }
}