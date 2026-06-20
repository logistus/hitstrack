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
        Schema::create('banner_rotator_banner', function (Blueprint $table) {
            $table->id();
            $table->foreignId('banner_rotator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();

            $table->unique(['banner_rotator_id', 'banner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_rotator_banner');
    }
};
