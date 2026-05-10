<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('templates')
            ->where('signing_method', 'docutrust_sign')
            ->update(['signing_method' => 'account_verified']);

        DB::table('templates')
            ->where('signing_method', 'email')
            ->update(['signing_method' => 'email_link']);
    }

    public function down(): void
    {
        DB::table('templates')
            ->where('signing_method', 'account_verified')
            ->update(['signing_method' => 'docutrust_sign']);

        DB::table('templates')
            ->where('signing_method', 'email_link')
            ->update(['signing_method' => 'email']);
    }
};
