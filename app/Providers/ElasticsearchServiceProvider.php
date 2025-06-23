<?php

namespace App\Providers;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;
use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Client::class, function ($app) {
            return ClientBuilder::create()
                ->setHosts(config('elasticsearch.connections.default.hosts'))
                ->setRetries(config('elasticsearch.connections.default.retries'))
                ->build();
        });
    }

    public function boot()
    {
        //
    }
}
