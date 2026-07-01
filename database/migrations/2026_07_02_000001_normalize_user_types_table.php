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
        if (! Schema::hasTable('user_types')) {
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
        }

        if (! Schema::hasColumn('user_types', 'max_link_trackers')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_link_trackers')->nullable()->after('label');
            });
        }

        if (! Schema::hasColumn('user_types', 'max_link_rotators')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_link_rotators')->nullable()->after('max_link_trackers');
            });
        }

        if (! Schema::hasColumn('user_types', 'max_banner_trackers')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_banner_trackers')->nullable()->after('max_link_rotators');
            });
        }

        if (! Schema::hasColumn('user_types', 'max_banner_rotators')) {
            Schema::table('user_types', function (Blueprint $table) {
                $table->unsignedInteger('max_banner_rotators')->nullable()->after('max_banner_trackers');
            });
        }

        foreach ([
            'free' => [
                'label' => 'Free',
                'max_link_trackers' => 5,
                'max_link_rotators' => 2,
                'max_banner_trackers' => 2,
                'max_banner_rotators' => 1,
            ],
            'premium' => [
                'label' => 'Premium',
                'max_link_trackers' => 100,
                'max_link_rotators' => 50,
                'max_banner_trackers' => 100,
                'max_banner_rotators' => 50,
            ],
            'admin' => [
                'label' => 'Admin',
                'max_link_trackers' => null,
                'max_link_rotators' => null,
                'max_banner_trackers' => null,
                'max_banner_rotators' => null,
            ],
        ] as $name => $attributes) {
            DB::table('user_types')->updateOrInsert(
                ['name' => $name],
                [
                    ...$attributes,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        $freeUserTypeId = DB::table('user_types')->where('name', 'free')->value('id');

        if (! Schema::hasColumn('users', 'user_type_id')) {
            Schema::table('users', function (Blueprint $table) use ($freeUserTypeId) {
                $table->foreignId('user_type_id')
                    ->default($freeUserTypeId)
                    ->after(Schema::hasColumn('users', 'user_type') ? 'user_type' : 'email_verified_at')
                    ->constrained('user_types')
                    ->restrictOnDelete();
            });
        }

        if (Schema::hasColumn('users', 'user_type')) {
            foreach (['free', 'premium', 'admin'] as $name) {
                $userTypeId = DB::table('user_types')->where('name', $name)->value('id');

                DB::table('users')
                    ->where('user_type', $name)
                    ->update(['user_type_id' => $userTypeId]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('user_type');
            });
        }
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
