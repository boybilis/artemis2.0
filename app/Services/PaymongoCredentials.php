<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PaymongoCredentials
{
    public function get(): array
    {
        $stored = DB::table('payment_credentials')->where('provider', 'paymongo')->value('encrypted_credentials');
        if ($stored !== null) {
            return json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
        }

        return [
            'secret_key' => config('services.paymongo.secret_key'),
            'webhook_secret' => config('services.paymongo.webhook_secret'),
        ];
    }

    public function save(string $secretKey, string $webhookSecret): void
    {
        $encrypted = Crypt::encryptString(json_encode([
            'secret_key' => $secretKey,
            'webhook_secret' => $webhookSecret,
        ], JSON_THROW_ON_ERROR));
        DB::table('payment_credentials')->upsert([
            ['provider' => 'paymongo', 'encrypted_credentials' => $encrypted],
        ], ['provider'], ['encrypted_credentials']);
    }

    public function status(): string
    {
        try {
            $credentials = $this->get();
            if (!filled($credentials['secret_key']) || !filled($credentials['webhook_secret'])) return 'Not fully configured';

            return str_starts_with($credentials['secret_key'], 'sk_live_') ? 'Live credentials configured' : 'Test credentials configured';
        } catch (\Throwable) {
            return 'Stored credentials could not be read';
        }
    }
}
