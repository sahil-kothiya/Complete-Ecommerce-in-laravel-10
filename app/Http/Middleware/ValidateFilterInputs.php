<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateFilterInputs
{
    public function handle(Request $request, Closure $next)
    {
        $validator = Validator::make($request->all(), [
            'brand.*' => 'nullable|string|exists:brands,slug',
            'price_range' => 'nullable|regex:/^\d+-\d+$/',
            'min_rating.*' => 'nullable|integer|min:1|max:5',
            'min_discount.*' => 'nullable|integer|min:0|max:100',
            'sortBy' => 'nullable|in:latest,price_low_high,price_high_low,rating_high_low,name_a_z,name_z_a',
            'show' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'category_slug' => 'nullable|string', // Remove max:255 to allow encrypted slugs
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter parameters',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $next($request);
    }
}