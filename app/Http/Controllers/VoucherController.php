<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Voucher;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\VoucherPurchased;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseBatch;

class VoucherController extends Controller
{
    private function generateVoucherCode()
    {
        $seg = function() {
            return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
        };
        return "ART2-" . $seg() . "-" . $seg();
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

        $code = $this->generateVoucherCode();

        // Create the voucher as pending
        $voucher = Voucher::create([
            'batch_id' => $batch?->id,
            'code' => $code,
            'price' => $batch->price,
            'duration_days' => $batch->ends_at ? max(1, now()->diffInDays($batch->ends_at)) : 30,
            'status' => 'pending_payment',
            'used' => false,
            'used_by' => $user->id,
            'used_at' => null
        ]);

        // Create Xendit Invoice
        $secretKey = env('XENDIT_SECRET_KEY');
        
        $response = Http::withBasicAuth($secretKey, '')
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $code,
                'amount' => (float) $batch->price,
                'currency' => 'PHP',
                'payer_email' => $user->email,
                'description' => 'Artemis 2.0 batch enrollment: ' . $batch->name . ' (' . $course->title . ')',
                'success_redirect_url' => url('/api/voucher/xendit/success?code=' . $code),
                'failure_redirect_url' => url('/')
            ]);

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'checkout_url' => $response->json()['invoice_url']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to generate checkout link.'
        ], 500);
    }

    public function xenditSuccess(Request $request)
    {
        $code = $request->query('code');
        if (!$code) {
            return redirect('/');
        }

        $voucher = Voucher::where('code', $code)->first();
        if (!$voucher || $voucher->status !== 'pending_payment') {
            return redirect('/?voucher_success=' . $code); // Already processed
        }

        // Verify with Xendit
        $secretKey = env('XENDIT_SECRET_KEY');
        $response = Http::withBasicAuth($secretKey, '')
            ->get('https://api.xendit.co/v2/invoices?external_id=' . $code);

        if ($response->successful()) {
            $invoices = $response->json();
            \Illuminate\Support\Facades\Log::info('Xendit Invoices:', $invoices);
            if (count($invoices) > 0 && in_array($invoices[0]['status'], ['PAID', 'SETTLED'])) {
                $voucher->status = 'active';
                $voucher->used = true;
                $voucher->used_at = Carbon::now();
                $voucher->redeemed_at = Carbon::now();
                $voucher->save();

                // Activate subscription for the user
                $user = \App\Models\User::find($voucher->used_by);
                if ($user) {
                    CourseEnrollment::updateOrCreate(
                        ['user_id' => $user->id, 'batch_id' => $voucher->batch_id],
                        ['voucher_id' => $voucher->id, 'batch_id'=>$voucher->batch_id, 'status' => 'active', 'enrolled_at' => now(),
                         'expires_at' => $voucher->batch?->ends_at ?: now()->addDays($voucher->duration_days ?: 30)]
                    );

                    \Illuminate\Support\Facades\Log::info('Sending email to: ' . $user->email);
                    try {
                        Mail::to($user->email)->send(new VoucherPurchased($voucher, $user));
                        \Illuminate\Support\Facades\Log::info('Email sent.');
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Mail Error: ' . $e->getMessage());
                    }
                } else {
                    \Illuminate\Support\Facades\Log::error('User not found for voucher.');
                }

                AuditLog::create([
                    'user_id' => $voucher->used_by,
                    'action' => 'Subscription Purchase',
                    'description' => 'Purchased enrollment for ' . ($voucher->batch?->name ?? 'batch') . ' via Xendit.',
                    'ip_address' => $request->ip()
                ]);

                return redirect('/?voucher_success=' . $code);
            } else {
                \Illuminate\Support\Facades\Log::info('Status not PAID. Redirecting to error.');
            }
        } else {
             \Illuminate\Support\Facades\Log::error('Xendit verification failed.');
        }

        return redirect('/?error=payment_not_completed');
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
