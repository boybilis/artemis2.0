<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class TestBankController extends Controller
{
    public function enrolled()
    {
        $enrollments = Auth::user()->testBankEnrollments()
            ->with('testBank.course:id,title')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('testBank', fn ($query) => $query->where('status', 'active'))
            ->latest('enrolled_at')->get();

        return response()->json(['success'=>true, 'testBanks'=>$enrollments->map(fn ($enrollment) => [
            'id'=>$enrollment->testBank->id, 'title'=>$enrollment->testBank->title,
            'code'=>$enrollment->testBank->code, 'description'=>$enrollment->testBank->description,
            'course'=>[
                'id'=>$enrollment->testBank->course?->id,
                'title'=>$enrollment->testBank->course?->title,
            ],
            'startsAt'=>$enrollment->testBank->starts_at?->toIso8601String(),
            'endsAt'=>$enrollment->testBank->ends_at?->toIso8601String(),
            'enrolledAt'=>$enrollment->enrolled_at?->toIso8601String(),
            'expiresAt'=>$enrollment->expires_at?->toIso8601String(),
        ])->values()]);
    }
}
