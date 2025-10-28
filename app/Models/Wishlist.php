<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'variant_id',
        'cart_id',
        'price',
        'quantity',
        'amount'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'amount' => 'decimal:2',
        'quantity' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }
}