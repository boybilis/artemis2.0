<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class BatchExchangeRates
{
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED', 'SAR', 'JPY'];

    public function quote(string $currency, float $amount): array
    {
        $key = 'batch-fx:php:'.$currency;
        $quote = Cache::get($key);
        if (!$quote) {
            try {
                $data = Http::acceptJson()->connectTimeout(2)->timeout(5)
                    ->get('https://api.frankfurter.dev/v2/rate/PHP/'.$currency)->throw()->json();
                $date = $data['date'] ?? '';
                $rate = (float) ($data['rate'] ?? 0);
                if (($data['base'] ?? '') === 'PHP' && ($data['quote'] ?? '') === $currency
                    && is_finite($rate) && $rate > 0 && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)
                    && $date >= now()->subDays(7)->toDateString() && $date <= now()->toDateString()) {
                    $quote = ['rate'=>$rate, 'date'=>$date];
                    Cache::put($key, $quote, now()->addHour());
                }
            } catch (\Illuminate\Http\Client\HttpClientException) {
                // Leave the existing batch price unchanged if a quote is unavailable.
            }
        }
        if (!$quote) throw ValidationException::withMessages(['international_currency'=>'An up-to-date exchange rate is unavailable. Please try again.']);

        return ['currency'=>$currency, 'amount'=>round($amount * $quote['rate'], $currency === 'JPY' ? 0 : 2)] + $quote;
    }
}
