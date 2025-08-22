<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'price', 'brand'
            $table->string('title'); // e.g., 'Price Range', 'Brands'
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filters');
    }
};
