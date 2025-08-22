<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Filter extends Model
{
    protected $fillable = [
        'name',
        'title',
        'description',
        'status',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_filter');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
