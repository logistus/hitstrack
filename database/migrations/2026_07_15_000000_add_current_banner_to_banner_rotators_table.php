<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner_rotators', function (Blueprint $table) {
            $table->foreignId('current_banner_id')
                ->nullable()
                ->after('rotation_type')
                ->constrained('banners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('banner_rotators', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_banner_id');
        });
    }
};
