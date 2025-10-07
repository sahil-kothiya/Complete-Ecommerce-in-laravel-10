<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'display_name', 'sort_order', 'status'];

    public function options()
    {
        return $this->hasMany(ProductVariantOption::class, 'variant_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
