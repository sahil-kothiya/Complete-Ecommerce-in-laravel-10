<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->string('photo')->nullable();

            // Parent-child relationship
            $table->unsignedBigInteger('parent_id')->nullable();

            // Tree structure optimization fields
            $table->unsignedInteger('level')->default(0); // Depth level (0 for root)
            $table->text('path')->nullable(); // Store full path like "1/2/3/4"
            $table->unsignedInteger('sort_order')->default(0); // For ordering siblings

            // Performance optimization fields
            $table->boolean('has_children')->default(false);
            $table->unsignedInteger('children_count')->default(0);
            $table->unsignedInteger('products_count')->default(0);

            // Status and metadata
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('enabled_filters')->nullable();

            // User tracking
            $table->unsignedBigInteger('added_by')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['parent_id', 'status']);
            $table->index(['level', 'status']);
            $table->index(['path']);
            $table->index(['sort_order']);
            $table->index(['has_children']);

            // Foreign key constraints
            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('added_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('categories');
    }
}
