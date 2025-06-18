<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWishlistsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('product_id')->constrained('products')->onDelete('CASCADE');

            // Nullable foreign keys
            $table->foreignId('cart_id')->nullable()->constrained('carts')->onDelete('SET NULL');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('SET NULL');

            // Data columns
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->decimal('amount', 10, 2);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wishlists');
    }
}
