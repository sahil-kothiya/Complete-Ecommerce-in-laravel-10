<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('post_comments', function (Blueprint $table) {
            $table->id();

            // Nullable foreign keys for SET NULL
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('SET NULL');
            $table->foreignId('post_id')->nullable()->constrained('posts')->onDelete('SET NULL');

            $table->text('comment');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('replied_comment')->nullable();

            // Parent comment for nesting/replies
            $table->bigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('post_comments')->onDelete('SET NULL');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_comments');
    }
}
