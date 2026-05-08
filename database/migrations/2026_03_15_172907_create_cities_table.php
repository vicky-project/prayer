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
      $table->decimal('latitude', 10, 8)->nullable();
      $table->decimal('longitude', 11, 8)->nullable();
      $table->string('timezone')->nullable(); // tambahan
      $table->timestamps();

      $table->index(['latitude', 'longitude']);
      $table->index("name");
    });
  }

  public function down() {
    Schema::dropIfExists('prayer_cities');
  }
};