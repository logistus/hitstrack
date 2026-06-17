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
        Schema::create('rotator_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rotator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_id')->nullable()->constrained()->nullOnDelete(); // o anda hangi tracker'a yönlendirildi
            $table->string('ref_url')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rotator_stats');
    }
};
