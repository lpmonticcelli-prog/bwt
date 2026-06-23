<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function cities()
    {
        return $this->belongsToMany(City::class);
    }

    public function pricingRules()
    {
        return $this->hasMany(PricingRule::class);
    }
}