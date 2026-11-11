<?php

namespace App\Http\Middleware;

use App\Services\AutomaticPayments;
use Closure;
use Illuminate\Http\Request;

class AutomaticPaymentMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!app(AutomaticPayments::class)->enabled()) {
            return response()->json(['success'=>false, 'code'=>'automatic_payment_disabled', 'message'=>AutomaticPayments::MESSAGE], 503);
        }
        return $next($request);
    }
}
