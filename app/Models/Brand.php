<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['title', 'slug', 'status', 'code', 'code_locked', 'code_generated_at'];

    // public static function getProductByBrand($id){
    //     return Product::where('brand_id',$id)->paginate(10);
    // }
    public function products()
    {
        return $this->hasMany('App\Models\Product', 'brand_id', 'id')->where('status', 'active');
    }
    // public static function getProductByBrand($slug)
    // {
    //     // dd($slug);
    //     return Brand::with(['products' => function ($query) {
    //         $query->where('status', 'active');
    //     }])->where('slug', $slug)->paginate(10);

    //     return Brand::with('products')->where('slug', $slug)->first();
    //     // return Product::where('cat_id',$id)->where('child_cat_id',null)->paginate(10);
    // }

    public static function getProductByBrand($slug)
    {
        return Product::with('brand:id,title,slug')
            ->whereHas('brand', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->where('status', 'active')
            ->paginate(9)
            ->appends(request()->query()); // Keeps filters in pagination links
    }


}
