<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Voucher;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\VoucherPurchased;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseBatch;
use App\Models\User;
use App\Models\PaymentTransaction;

class VoucherController extends Controller
{
    private function generateVoucherCode()
    {
        $seg = function() {
            return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
        };
        return "ART2-" . $seg() . "-" . $seg();
    }

    private function generatePaymentReference(): string
    {
        do {
            $reference = 'ART2PAY-' . strtoupper(bin2hex(random_bytes(6)));
        } while (PaymentTransaction::where('reference', $reference)->exists());

        return $reference;
    }

    public function buy(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Must be logged in'], 401);
        }

        $data = $request->validate(['batch_id'=>'required|integer|exists:course_batches,id']);
        $batch = CourseBatch::available()->findOrFail($data['batch_id']);
        $course = Course::available()->findOrFail($batch->course_id);

        if ($user->hasActiveEnrollment($course->id)) {
            return response()->json(['success' => false, 'message' => 'You already have active access to this course.'], 422);
        }

        $reference = $this->generatePaymentReference();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'reference' => $reference,
            'amount' => $batch->price,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'status' => 'pending_payment',
        ]);

        $secretKey = config('services.paymongo.secret_key');
        if (!$secretKey) {
            $transaction->update(['status' => 'payment_configuration_error']);
            return response()->json(['success' => false, 'message' => 'Online payment is not configured yet.'], 503);
        }

        $description = 'Artemis 2.0 batch enrollment: ' . $batch->name . ' (' . $course->title . ')';
        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->post('https://api.paymongo.com/v1/checkout_sessions', [
                'data' => [
                    'attributes' => [
                        'billing' => array_filter([
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                        ]),
                        'cancel_url' => url('/?payment_cancelled=1'),
                        'description' => $description,
                        'line_items' => [[
                            'amount' => (int) round(((float) $batch->price) * 100),
                            'currency' => 'PHP',
                            'description' => $description,
                            'name' => $batch->name,
                            'quantity' => 1,
                        ]],
                        'payment_method_types' => config('services.paymongo.payment_methods', ['qrph']),
                        'reference_number' => $reference,
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,
                        'success_url' => route('payments.paymongo.success', ['reference' => $reference]),
                    ],
                ],
            ]);

        if ($response->successful()) {
            $checkout = $response->json('data');
            $transaction->update([
                'provider_checkout_id' => $checkout['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $checkout['attributes']['checkout_url'] ?? null,
            ]);
        }

        $transaction->update(['status' => 'payment_creation_failed']);
        Log::error('PayMongo checkout creation failed.', [
            'payment_transaction_id' => $transaction->id,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'PayMongo could not create the QR Ph checkout. Please try again.'
        ], 500);
    }

    public function paymongoSuccess(Request $request)
    {
        $reference = strtoupper(trim((string) ($request->query('reference') ?: $request->query('code'))));
        if (!$reference) {
            return redirect('/');
        }

        $transaction = PaymentTransaction::where('reference', $reference)->first();
        $legacyVoucher = $transaction ? null : Voucher::where('code', $reference)->where('payment_provider', 'paymongo')->first();
        if (!$transaction && !$legacyVoucher) {
            return redirect('/?error=payment_not_found');
        }

        if ($transaction?->status === 'paid' || $legacyVoucher?->used) {
            $code = $transaction ? $this->voucherCodeForTransaction($transaction) : $legacyVoucher->code;
            return redirect('/?voucher_success=' . urlencode($code));
        }

        $secretKey = config('services.paymongo.secret_key');
        $checkoutId = $transaction?->provider_checkout_id ?: $legacyVoucher?->provider_checkout_id;
        if (!$secretKey || !$checkoutId) {
            return redirect('/?error=payment_not_completed');
        }

        $response = Http::withBasicAuth($secretKey, '')
            ->acceptJson()
            ->get('https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($checkoutId));

        if ($response->successful() && $this->checkoutIsPaid($response->json('data.attributes', []))) {
            $paymentId = data_get($response->json(), 'data.attributes.payments.0.id');
            $code = $transaction
                ? $this->activatePaidTransaction($transaction, $request->ip(), $paymentId)->code
                : tap($legacyVoucher, fn ($voucher) => $this->activatePaidVoucher($voucher, $request->ip(), $paymentId))->code;
            return redirect('/?voucher_success=' . urlencode($code));
        }

        Log::warning('PayMongo return did not contain a paid checkout.', ['reference' => $reference]);
        return redirect('/?error=payment_not_completed');
    }

    public function paymongoWebhook(Request $request)
    {
        $rawPayload = $request->getContent();
        if (!$this->validPaymongoSignature($rawPayload, (string) $request->header('Paymongo-Signature'))) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $event = $request->json()->all();
        $eventType = data_get($event, 'data.attributes.type');
        if (!in_array($eventType, ['checkout_session.payment.paid', 'payment.paid'], true)) {
            return response()->json(['received' => true]);
        }

        $resource = data_get($event, 'data.attributes.data', []);
        $attributes = data_get($resource, 'attributes', []);
        $checkoutId = data_get($resource, 'type') === 'checkout_session' ? data_get($resource, 'id') : null;
        $code = strtoupper(trim((string) ($attributes['reference_number'] ?? $attributes['external_reference_number'] ?? '')));
        $transaction = $checkoutId ? PaymentTransaction::where('provider_checkout_id', $checkoutId)->first() : null;
        $transaction ??= $code ? PaymentTransaction::where('reference', $code)->first() : null;
        $legacyVoucher = $transaction ? null : ($checkoutId ? Voucher::where('provider_checkout_id', $checkoutId)->first() : null);
        $legacyVoucher ??= (!$transaction && $code) ? Voucher::where('code', $code)->where('payment_provider', 'paymongo')->first() : null;

        if (!$transaction && !$legacyVoucher) {
            Log::warning('PayMongo paid webhook could not be matched.', ['event_id' => data_get($event, 'data.id')]);
            return response()->json(['received' => true]);
        }

        $paymentId = data_get($resource, 'type') === 'payment'
            ? data_get($resource, 'id')
            : data_get($attributes, 'payments.0.id');
        if ($transaction) $this->activatePaidTransaction($transaction, $request->ip(), $paymentId);
        else $this->activatePaidVoucher($legacyVoucher, $request->ip(), $paymentId);

        return response()->json(['received' => true]);
    }

    private function checkoutIsPaid(array $attributes): bool
    {
        foreach (($attributes['payments'] ?? []) as $payment) {
            if (data_get($payment, 'attributes.status') === 'paid') return true;
        }

        return in_array(data_get($attributes, 'payment_intent.attributes.status'), ['succeeded', 'paid'], true)
            || in_array($attributes['status'] ?? null, ['paid', 'succeeded'], true);
    }

    private function validPaymongoSignature(string $payload, string $header): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        if ($secret === '' || $header === '') return false;

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value) $parts[$key] = $value;
        }

        $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
        if (!$timestamp || abs(time() - $timestamp) > config('services.paymongo.webhook_tolerance', 300)) return false;

        $signatureKey = str_starts_with((string) config('services.paymongo.secret_key'), 'sk_live_') ? 'li' : 'te';
        $signature = $parts[$signatureKey] ?? null;
        return $signature && hash_equals(hash_hmac('sha256', $timestamp . '.' . $payload, $secret), $signature);
    }

    private function voucherCodeForTransaction(PaymentTransaction $transaction): string
    {
        return Voucher::where('provider_checkout_id', $transaction->provider_checkout_id)
            ->value('code') ?: $transaction->reference;
    }

    private function activatePaidTransaction(PaymentTransaction $transaction, ?string $ipAddress, ?string $paymentId = null): Voucher
    {
        $created = false;
        [$transaction, $voucher] = DB::transaction(function () use ($transaction, $paymentId, &$created) {
            $locked = PaymentTransaction::with(['batch', 'user'])->lockForUpdate()->findOrFail($transaction->id);
            $existingVoucher = Voucher::where('provider_checkout_id', $locked->provider_checkout_id)->first();
            if ($locked->status === 'paid' && $existingVoucher) return [$locked, $existingVoucher];
            if (!$locked->user || !$locked->batch) {
                throw new \RuntimeException('Paid transaction is missing its learner or batch.');
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_payment_id' => $paymentId ?: $locked->provider_payment_id,
            ]);

            $voucher = $existingVoucher ?: Voucher::create([
                'batch_id' => $locked->batch_id,
                'code' => $this->generateVoucherCode(),
                'price' => $locked->amount,
                'duration_days' => $locked->batch->ends_at ? max(1, now()->diffInDays($locked->batch->ends_at)) : 30,
                'status' => 'active',
                'used' => true,
                'used_by' => $locked->user_id,
                'used_at' => now(),
                'redeemed_at' => now(),
                'payment_provider' => 'paymongo',
                'provider_checkout_id' => $locked->provider_checkout_id,
                'provider_payment_id' => $paymentId,
            ]);

            CourseEnrollment::updateOrCreate(
                ['user_id' => $locked->user_id, 'batch_id' => $locked->batch_id],
                ['voucher_id' => $voucher->id, 'status' => 'active', 'enrolled_at' => now(),
                    'expires_at' => $locked->batch->ends_at ?: now()->addDays($voucher->duration_days ?: 30)]
            );
            $created = !$existingVoucher;
            return [$locked, $voucher];
        });

        if (!$created) return $voucher;

        AuditLog::create([
            'user_id' => $transaction->user_id,
            'action' => 'Subscription Purchase',
            'description' => 'Purchased enrollment for ' . ($transaction->batch?->name ?? 'batch') . ' via PayMongo QR Ph.',
            'ip_address' => $ipAddress,
        ]);

        if ($user = User::find($transaction->user_id)) {
            try {
                Mail::to($user->email)->send(new VoucherPurchased($voucher, $user));
            } catch (\Throwable $e) {
                Log::error('PayMongo receipt email failed.', ['voucher_id' => $voucher->id, 'error' => $e->getMessage()]);
            }
        }

        return $voucher;
    }

    private function activatePaidVoucher(Voucher $voucher, ?string $ipAddress, ?string $paymentId = null): void
    {
        $sendReceipt = false;
        $voucher = DB::transaction(function () use ($voucher, $paymentId, &$sendReceipt) {
            $locked = Voucher::with('batch')->lockForUpdate()->findOrFail($voucher->id);
            if ($locked->used) return $locked;

            $user = User::find($locked->used_by);
            if (!$user || !$locked->batch) {
                throw new \RuntimeException('Paid enrollment is missing its learner or batch.');
            }

            $locked->update([
                'status' => 'active',
                'used' => true,
                'used_at' => now(),
                'redeemed_at' => now(),
                'provider_payment_id' => $paymentId ?: $locked->provider_payment_id,
            ]);

            CourseEnrollment::updateOrCreate(
                ['user_id' => $user->id, 'batch_id' => $locked->batch_id],
                ['voucher_id' => $locked->id, 'status' => 'active', 'enrolled_at' => now(),
                    'expires_at' => $locked->batch->ends_at ?: now()->addDays($locked->duration_days ?: 30)]
            );
            $sendReceipt = true;
            return $locked;
        });

        if (!$sendReceipt) return;

        AuditLog::create([
            'user_id' => $voucher->used_by,
            'action' => 'Subscription Purchase',
            'description' => 'Purchased enrollment for ' . ($voucher->batch?->name ?? 'batch') . ' via PayMongo QR Ph.',
            'ip_address' => $ipAddress,
        ]);

        $user = User::find($voucher->used_by);
        if ($user) {
            try {
                Mail::to($user->email)->send(new VoucherPurchased($voucher, $user));
            } catch (\Throwable $e) {
                Log::error('PayMongo receipt email failed.', ['voucher_id' => $voucher->id, 'error' => $e->getMessage()]);
            }
        }
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'batch_id' => 'required|integer|exists:course_batches,id',
        ]);

        $code = strtoupper(trim($request->input('code')));
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription code.'
            ], 404);
        }

        if ($voucher->used) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription code has already been redeemed.'
            ], 400);
        }

        $requestedBatch = CourseBatch::findOrFail($request->integer('batch_id'));
        if ($voucher->batch_id && (int) $voucher->batch_id !== (int) $requestedBatch->id) return response()->json(['success'=>false,'message'=>'This code belongs to a different batch.'], 422);

        return response()->json([
            'success' => true,
            'message' => 'Enrollment code is valid and ready to activate.',
            'courseId' => $voucher->batch?->course_id,
            'courseName' => $voucher->batch?->course?->title,
            'batchId' => $voucher->batch_id,
            'batchName' => $voucher->batch?->name,
        ]);
    }

    public function redeem(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $request->validate([
            'code' => 'required|string',
            'batch_id' => 'required|integer|exists:course_batches,id',
        ]);

        $code = strtoupper(trim($request->input('code')));
        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid subscription code.'
            ], 404);
        }

        if ($voucher->used) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription code has already been redeemed.'
            ], 400);
        }

        $batchId = $voucher->batch_id ?: $request->integer('batch_id');
        $batch = CourseBatch::available()->findOrFail($batchId);
        $courseId = $batch->course_id;

        // Mark as used
        $voucher->update([
            'used' => true,
            'used_by' => $user->id,
            'used_at' => Carbon::now(),
            'redeemed_at' => Carbon::now(),
            'batch_id' => $batch?->id,
        ]);

        $durationDays = $voucher->duration_days ?: 30;
        $existing = CourseEnrollment::where('user_id', $user->id)->where('batch_id', $batch->id)->first();
        $course = Course::available()->findOrFail($courseId);
        $base = $existing?->expires_at && $existing->expires_at->isFuture() ? $existing->expires_at : now();
        $enrollment = CourseEnrollment::updateOrCreate(
            ['user_id' => $user->id, 'batch_id' => $batch->id],
            ['voucher_id' => $voucher->id, 'status' => 'active', 'enrolled_at' => now(),
             'expires_at' => $batch->ends_at ?: $base->copy()->addDays($durationDays)]
        );

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'Subscription Activated',
            'description' => 'Redeemed enrollment code ' . $code . ' for ' . Course::find($courseId)?->title . '.',
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch enrollment activated successfully. The assigned course is now available.',
            'courseId' => (int) $courseId,
            'batchId' => $batch?->id,
            'batchName' => $batch?->name,
            'enrollmentExpiresAt' => $enrollment->expires_at?->toIso8601String(),
        ]);
    }
}
