<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlacesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('places', function (Blueprint $table) {
            $table->increments('id');
            $table->string('fb_id')->nullable();
            $table->string('name');
            $table->float('lat', 16, 12); // decimal part is guaranteed to be smaller or equal than 3 digits
            $table->float('long', 16, 12); // decimal part is guaranteed to be smaller or equal than 3 digits
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->unique('fb_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('places');
    }
}
