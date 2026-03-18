<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up() {
    Schema::create('prayers', function (Blueprint $table) {
      $table->id();
      $table->string('prayer_id')->unique(); // ID asli dari JSON
      $table->foreignId('city_id')->constrained()->onDelete('cascade');
      $table->date('date');
      $table->string('imsak')->nullable();
      $table->string('subuh')->nullable();
      $table->string('terbit')->nullable();
      $table->string('dhuha')->nullable();
      $table->string('dzuhur')->nullable();
      $table->string('ashar')->nullable();
      $table->string('maghrib')->nullable();
      $table->string('isya')->nullable();
      $table->timestamps();

      $table->index(['city_id', 'date']);
    });
  }

  public function down() {
    Schema::dropIfExists('prayers');
  }
};