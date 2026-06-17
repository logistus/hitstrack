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
        Schema::create('rotator_tracker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('weight')->default(1);       // weighted rotasyon için
            $table->unsignedInteger('order_column')->default(0); // round robin sıralaması için
            $table->timestamps();

            $table->unique(['rotator_id', 'tracker_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotator_tracker');
    }
};
