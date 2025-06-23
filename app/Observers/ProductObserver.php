<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ElasticsearchService;
use App\Services\RedisCacheManager;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }
    public function saved(Product $product): void
    {
        RedisCacheManager::forget('page', 'home');
        RedisCacheManager::put('product', $product->id, $product->toArray());
    }
    public function created(Product $product)
    {
        if ($product->status === 'active') {
            $this->elasticsearch->indexProduct($product->toSearchableArray());
        }
    }

    public function updated(Product $product)
    {
        if ($product->status === 'active') {
            $this->elasticsearch->indexProduct($product->toSearchableArray());
        }
    }

    public function deleted(Product $product)
    {
        try {
            $client = app(Client::class);
            $client->delete([
                'index' => config('elasticsearch.index'),
                'id' => $product->id
            ]);
            $this->clearProductCache($product->id);
        } catch (\Exception $e) {
            Log::error('Failed to delete product from Elasticsearch: ' . $e->getMessage());
        }
    }

    public function forceDeleted(Product $product): void
    {
        $this->clearProductCache($product->id);
    }

    public function restored(Product $product): void
    {
        RedisCacheManager::put('product', $product->id, $product->toArray());
    }

    protected function clearProductCache(int|string $id): void
    {
        RedisCacheManager::forget('product', $id);
    }
}
