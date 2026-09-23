<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestBankEnrollment extends Model
{
    protected $fillable = ['test_bank_id', 'user_id', 'status', 'enrolled_at', 'expires_at'];
    protected $casts = ['enrolled_at'=>'datetime', 'expires_at'=>'datetime'];

    public function testBank() { return $this->belongsTo(TestBank::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function isActive(): bool
    {
        return $this->status === 'active' && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public static function activate(TestBank $testBank, User $user): self
    {
        $activatedAt = now();
        $existing = static::where('test_bank_id', $testBank->id)->where('user_id', $user->id)->first();
        $accessStartsAt = $existing?->status === 'active' && $existing->expires_at?->isFuture()
            ? $existing->expires_at->copy()
            : $activatedAt->copy();

        return static::updateOrCreate(
            ['test_bank_id' => $testBank->id, 'user_id' => $user->id],
            [
                'status' => 'active',
                'enrolled_at' => $activatedAt,
                'expires_at' => $testBank->access_days
                    ? $accessStartsAt->addDays($testBank->access_days)
                    : null,
            ]
        );
    }
}
