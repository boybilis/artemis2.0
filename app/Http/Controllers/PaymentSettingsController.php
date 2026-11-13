<?php

namespace App\Http\Controllers;

use App\Mail\AssignedEnrollmentVoucher;
use App\Models\AuditLog;
use App\Models\CourseBatch;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AutomaticPayments;
use App\Services\PaymongoCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentSettingsController extends Controller
{
    public function credentials(Request $request, AutomaticPayments $payments, PaymongoCredentials $credentials)
    {
        abort_unless($request->user()->hasAdminAccess() && !$request->user()->isAssistantAdmin(), 403);
        if ($payments->enabled()) {
            return response()->json(['message' => 'Pause automatic payments before replacing PayMongo credentials.'], 409);
        }
        $validator = Validator::make($request->all(), [
            'paymongo_secret_key' => ['required', 'string', 'max:255', 'regex:/^sk_(test|live)_[a-zA-Z0-9_-]+$/D'],
            'paymongo_webhook_secret' => ['required', 'string', 'max:255', 'regex:/^whsk_[a-zA-Z0-9_-]+$/D'],
            'current_password' => ['required', 'string', 'current_password'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Check the PayMongo key formats and your current LMS password.'], 422);
        }
        $data = $validator->validated();
        $credentials->save($data['paymongo_secret_key'], $data['paymongo_webhook_secret']);
        $this->audit($request, 'PayMongo Credentials Updated', 'Replaced both PayMongo credentials.');

        return response()->json(['success' => true, 'message' => 'PayMongo credentials saved. Automatic payments remain paused.', 'status' => $credentials->status()]);
    }

    public function update(Request $request, AutomaticPayments $payments)
    {
        $request->validate(['enabled'=>'required|boolean']);
        $enabled = $request->boolean('enabled');
        abort_unless($payments->setEnabled($enabled), 500, 'Could not save the payment setting.');
        $this->audit($request, 'Automatic Payments Updated', $enabled ? 'Enabled automatic checkout.' : 'Paused automatic checkout.');

        return response()->json(['success'=>true, 'message'=>$enabled ? 'Automatic payments enabled.' : 'Automatic payments paused.']);
    }

    public function issue(Request $request)
    {
        $data = $request->validate(['email'=>'required|email|max:255', 'batch_id'=>'required|integer']);
        $learner = User::where('email', strtolower(trim($data['email'])))->where('role', 'student')->where('is_admin', false)->where('is_active', true)->first();
        if (!$learner) throw ValidationException::withMessages(['email'=>'No active learner was found with that email.']);
        $batch = CourseBatch::available()->has('courses')->find($data['batch_id']);
        if (!$batch) throw ValidationException::withMessages(['batch_id'=>'Select an available batch with an assigned course.']);

        do {
            $hex = strtoupper(bin2hex(random_bytes(4)));
            $code = 'ART2-'.substr($hex, 0, 4).'-'.substr($hex, 4);
        } while (Voucher::where('code', $code)->exists());

        $voucher = Voucher::create([
            'code'=>$code, 'assigned_to'=>$learner->id,
            'batch_id'=>$batch->id, 'price'=>0, 'used'=>false, 'status'=>'active', 'payment_provider'=>'manual',
        ]);
        $this->audit($request, 'Enrollment Voucher Issued', "Voucher {$voucher->id} assigned to learner {$learner->id} for batch {$batch->id}.");
        try {
            Mail::to($learner->email)->send(new AssignedEnrollmentVoucher($voucher));
            $voucher->update(['emailed_at'=>now()]);
        } catch (\Throwable $error) {
            report($error);
        }

        return response()->json(['success'=>true, 'emailed'=>(bool) $voucher->emailed_at, 'message'=>$voucher->emailed_at
            ? 'Voucher issued and emailed to the learner.'
            : 'Voucher issued, but email could not be confirmed. Share the saved code directly with the assigned learner.'], 201);
    }

    private function audit(Request $request, string $action, string $description): void
    {
        AuditLog::create(['user_id'=>$request->user()->id, 'action'=>$action, 'description'=>$description, 'ip_address'=>$request->ip()]);
    }
}
