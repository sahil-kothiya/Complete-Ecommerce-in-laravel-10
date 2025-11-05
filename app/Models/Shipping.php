<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    protected $fillable = ['type', 'price', 'status'];

    protected $casts = [
        'price'  => 'decimal:2',
        'status' => 'boolean',
    ];

    protected $attributes = [
        'price'  => 0.00,
        'status' => true,
    ];
}