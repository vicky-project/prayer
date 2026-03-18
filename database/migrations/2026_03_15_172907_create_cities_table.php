<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('cities', function (Blueprint $table) {
      $table->id();
      $table->string('city_id')->unique(); // ID asli dari JSON
      $table->string('name');
      $table->string('slug')->nullable();
      $table->string('province_id'); // ID provinsi dari JSON
      $table->string('province_name')->nullable();
      $table->decimal('latitude', 10, 8)->nullable();
      $table->decimal('longitude', 11, 8)->nullable();
      $table->timestamps();
    });
  }

  public function down() {
    Schema::dropIfExists('cities');
  }
};