<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class AutomaticPayments
{
    public const MESSAGE = 'Dear learner, Currently, the automatic payment is under maintenance, please contact our Administrator.';
    private const PATH = 'payments/automatic-mode.txt';

    public function enabled(): bool
    {
        $disk = Storage::disk('local');
        return !$disk->exists(self::PATH) || $disk->get(self::PATH) === 'on';
    }

    public function setEnabled(bool $enabled): bool
    {
        return Storage::disk('local')->put(self::PATH, $enabled ? 'on' : 'off');
    }
}
