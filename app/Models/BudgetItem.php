<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'valores_mensais' => 'array', 
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }
}