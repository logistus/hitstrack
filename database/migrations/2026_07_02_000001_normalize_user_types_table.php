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
                $table->timestamps();
            });
        }

        foreach ([
            'free' => 'Free',
            'premium' => 'Premium',
            'admin' => 'Admin',
        ] as $name => $label) {
            DB::table('user_types')->updateOrInsert(
                ['name' => $name],
                ['label' => $label, 'updated_at' => now(), 'created_at' => now()],
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
