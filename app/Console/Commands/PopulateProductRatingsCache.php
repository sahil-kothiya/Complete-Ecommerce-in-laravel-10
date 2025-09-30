<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PopulateProductRatingsCache extends Command
{
    protected $signature = 'ratings:cache';
    protected $description = 'Populate product_ratings_cache table with aggregated ratings';

    public function handle()
    {
        $this->info('Populating product_ratings_cache...');

        try {
            DB::statement('TRUNCATE TABLE product_ratings_cache RESTART IDENTITY');

            $ratings = DB::table('product_reviews')
                ->selectRaw('product_id, AVG(CAST(rate AS DECIMAL(3,2))) as average_rating, COUNT(*) as total_reviews')
                ->groupBy('product_id')
                ->get();

            $insertData = $ratings->map(function ($rating) {
                return [
                    'product_id' => $rating->product_id,
                    'average_rating' => round($rating->average_rating, 2),
                    'total_reviews' => $rating->total_reviews,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            foreach (array_chunk($insertData, 1000) as $chunk) {
                DB::table('product_ratings_cache')->insert($chunk);
            }

            $this->info('Successfully populated product_ratings_cache with ' . count($insertData) . ' records.');
        } catch (\Exception $e) {
            Log::error('Failed to populate product_ratings_cache: ' . $e->getMessage());
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}