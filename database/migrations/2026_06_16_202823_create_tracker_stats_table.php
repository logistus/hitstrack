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
        Schema::create('tracker_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rotator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ref_url')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_stats');
    }
};
