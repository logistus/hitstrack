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
            $table->unsignedInteger('max_link_trackers')->nullable();
            $table->unsignedInteger('max_link_rotators')->nullable();
            $table->unsignedInteger('max_banner_trackers')->nullable();
            $table->unsignedInteger('max_banner_rotators')->nullable();
            $table->timestamps();
        });

        DB::table('user_types')->insert([
            [
                'name' => 'free',
                'label' => 'Free',
                'max_link_trackers' => 5,
                'max_link_rotators' => 2,
                'max_banner_trackers' => 2,
                'max_banner_rotators' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'premium',
                'label' => 'Premium',
                'max_link_trackers' => 100,
                'max_link_rotators' => 50,
                'max_banner_trackers' => 100,
                'max_banner_rotators' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin',
                'label' => 'Admin',
                'max_link_trackers' => null,
                'max_link_rotators' => null,
                'max_banner_trackers' => null,
                'max_banner_rotators' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
