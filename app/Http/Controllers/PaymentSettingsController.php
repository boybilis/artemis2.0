<?php

namespace App\Http\Controllers;

use App\Mail\AssignedEnrollmentVoucher;
use App\Models\AuditLog;
use App\Models\CourseBatch;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AutomaticPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PaymentSettingsController extends Controller
{
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

        $voucher = Voucher::create([
            'code'=>'GRA-'.strtoupper(bin2hex(random_bytes(12))), 'assigned_to'=>$learner->id,
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
