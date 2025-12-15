<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('atvs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // e.g., '150cc', '450cc', 'Sport', 'Utility'
            $table->string('serial_number')->unique();
            $table->decimal('hourly_price', 10, 2);
            $table->enum('status', ['available', 'rented', 'maintenance'])->default('available');
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atvs');
    }
};

