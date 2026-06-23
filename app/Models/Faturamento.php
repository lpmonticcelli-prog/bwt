<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faturamento extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'temTde' => 'boolean',
        'is_correto' => 'boolean',
    ];
}