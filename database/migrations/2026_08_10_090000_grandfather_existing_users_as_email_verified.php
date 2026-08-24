<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Accounts that existed before registration verification was introduced
        // are grandfathered once. All newly registered accounts receive this
        // timestamp only after the six-digit code has been verified.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Verification is security-sensitive and must not be removed on rollback.
    }
};
