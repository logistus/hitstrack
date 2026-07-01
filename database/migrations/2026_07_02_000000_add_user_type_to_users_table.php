<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });

        DB::table('user_types')->insert([
            ['name' => 'free', 'label' => 'Free', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'premium', 'label' => 'Premium', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'label' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $freeUserTypeId = DB::table('user_types')->where('name', 'free')->value('id');

        Schema::table('users', function (Blueprint $table) use ($freeUserTypeId) {
            $table->foreignId('user_type_id')
                ->default($freeUserTypeId)
                ->after('email_verified_at')
                ->constrained('user_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'user_type_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_type_id');
            });
        }

        Schema::dropIfExists('user_types');
    }
};
