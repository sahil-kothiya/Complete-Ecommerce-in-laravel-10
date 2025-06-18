<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('description')->nullable();
            $table->integer('stock')->default(1);
            $table->string('size')->nullable()->default('M');
            $table->enum('condition', ['default', 'new', 'hot'])->default('default');
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->foreignId('cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('child_cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');
            $table->timestamps();

            // Optional: PostgreSQL index tuning
            $table->index(['status', 'is_featured']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
