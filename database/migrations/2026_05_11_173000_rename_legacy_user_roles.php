<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'notary_admin']);

        DB::table('users')
            ->where('role', 'signer')
            ->update(['role' => 'client']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'notary_admin')
            ->update(['role' => 'admin']);

        DB::table('users')
            ->where('role', 'client')
            ->update(['role' => 'signer']);
    }
};
