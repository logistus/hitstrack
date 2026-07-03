<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rotators') || Schema::hasColumn('rotators', 'rotator_name')) {
            return;
        }

        Schema::table('rotators', function (Blueprint $table) {
            $table->string('rotator_name')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rotators') || ! Schema::hasColumn('rotators', 'rotator_name')) {
            return;
        }

        Schema::table('rotators', function (Blueprint $table) {
            $table->dropColumn('rotator_name');
        });
    }
};
