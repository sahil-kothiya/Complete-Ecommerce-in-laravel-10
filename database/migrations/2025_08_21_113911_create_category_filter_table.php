<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_filter', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('filter_id');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('filter_id')->references('id')->on('filters')->onDelete('cascade');

            $table->unique(['category_id', 'filter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_filter');
    }
};
