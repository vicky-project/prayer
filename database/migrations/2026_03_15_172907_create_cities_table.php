<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('prayer_cities', function (Blueprint $table) {
      $table->id();
      $table->string('city_id')->unique();
      $table->string('name');
      $table->string('slug')->nullable();
      $table->string('province_id');
      $table->string('province_name')->nullable();
      $table->geography('coordinates', subtype: 'point');
      $table->string('timezone')->nullable(); // tambahan
      $table->timestamps();

      $table->index("name");
    });

    Schema::table('prayer_cities', function(Blueprint $table) {
      $table->spatialIndex('coordinates');
    });
  }

  public function down() {
    Schema::dropIfExists('prayer_cities');
  }
};