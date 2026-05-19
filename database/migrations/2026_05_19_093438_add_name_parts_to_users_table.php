<?php

use App\Models\User;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('suffix', 30)->nullable()->after('last_name');
        });

        User::query()->whereNull('first_name')->each(function (User $user): void {
            $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];

            if ($parts === []) {
                return;
            }

            $firstName = array_shift($parts);
            $lastName = $parts !== [] ? array_pop($parts) : $firstName;
            $middleName = $parts !== [] ? implode(' ', $parts) : null;

            $user->forceFill([
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
            ])->saveQuietly();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'suffix']);
        });
    }
};
