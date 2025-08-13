<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['title', 'slug', 'description', 'photo', 'status', 'link_type', 'link'];

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'banner_discount');
    }
}
