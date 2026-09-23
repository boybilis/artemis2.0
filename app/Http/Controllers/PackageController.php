<?php

namespace App\Http\Controllers;

use App\Models\CourseBatch;
use App\Models\PaymentTransaction;
use App\Models\ReviewPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PackageController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $packages = ReviewPackage::available()->with(['batches' => fn ($query) => $query->available()->with('courses')])->latest('starts_at')->get();

        return response()->json(['success' => true, 'packages' => $packages->map(function (ReviewPackage $package) use ($user) {
            $batchIds = $package->batches->pluck('id');
            $enrolledIds = $user->enrollments()->whereIn('batch_id', $batchIds)->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('batch_id');
            return [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => (float) $package->price,
                'starts_at' => $package->starts_at?->toDateString(),
                'class_type' => $package->class_type,
                // A package purchase grants one learner-selected batch. Once any
                // included batch is active, the package has already been used.
                'is_subscribed' => $enrolledIds->isNotEmpty(),
                'batches' => $package->batches->map(fn (CourseBatch $batch) => [
                    'id' => $batch->id, 'name' => $batch->name, 'code' => $batch->code,
                    'courses' => $batch->courses->pluck('title')->values(),
                    'is_enrolled' => $enrolledIds->contains($batch->id),
                ])->values(),
            ];
        })->values()]);
    }

    public function buy(Request $request, ReviewPackage $package)
    {
        $user = Auth::user();
        abort_unless($package->status === 'active', 404);
        $data = $request->validate(['batch_id'=>'required|integer|exists:course_batches,id']);
        $package->load(['batches' => fn ($query) => $query->available()->with('courses')]);
        abort_if($package->batches->isEmpty(), 422, 'This package has no currently available batch offerings.');
        $batch = $package->batches->firstWhere('id', (int) $data['batch_id']);
        abort_unless($batch, 422, 'Select an available batch included in this package.');

        $activeBatchIds = $user->enrollments()->whereIn('batch_id', $package->batches->pluck('id'))->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->pluck('batch_id');
        if ($activeBatchIds->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'You have already used this package for an included batch.'], 422);
        }

        do {
            $reference = 'ART2PKG-' . strtoupper(bin2hex(random_bytes(6)));
        } while (PaymentTransaction::where('reference', $reference)->exists());

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'batch_id'=>$batch->id, 'review_package_id' => $package->id, 'reference' => $reference,
            'amount' => $package->price, 'currency' => 'PHP', 'provider' => 'paymongo', 'status' => 'pending_payment',
        ]);
        $secretKey = config('services.paymongo.secret_key');
        if (!$secretKey) {
            $transaction->update(['status' => 'payment_configuration_error']);
            return response()->json(['success' => false, 'message' => 'Online payment is not configured yet.'], 503);
        }

        $description = 'Artemis 2.0 review package: ' . $package->name . ' — ' . $batch->name;
        $response = Http::withBasicAuth($secretKey, '')->acceptJson()->post('https://api.paymongo.com/v1/checkout_sessions', [
            'data' => ['attributes' => [
                'billing' => array_filter(['name'=>$user->name, 'email'=>$user->email]),
                'cancel_url' => url('/?payment_cancelled=1'), 'description' => $description,
                'line_items' => [[ 'amount'=>(int) round(((float) $package->price) * 100), 'currency'=>'PHP', 'description'=>$description, 'name'=>$package->name, 'quantity'=>1 ]],
                'payment_method_types' => config('services.paymongo.payment_methods', ['qrph']),
                'reference_number' => $reference, 'send_email_receipt'=>true, 'show_description'=>true, 'show_line_items'=>true,
                'success_url' => route('payments.paymongo.success', ['reference'=>$reference]),
            ]],
        ]);

        if (!$response->successful()) {
            $transaction->update(['status' => 'payment_creation_failed']);
            Log::error('PayMongo package checkout failed.', ['transaction'=>$transaction->id, 'response'=>$response->json()]);
            return response()->json(['success'=>false, 'message'=>'PayMongo could not create the package checkout. Please try again.'], 500);
        }
        $checkout = $response->json('data');
        $transaction->update(['provider_checkout_id' => $checkout['id'] ?? null]);
        return response()->json(['success'=>true, 'checkout_url'=>$checkout['attributes']['checkout_url'] ?? null]);
    }
}
