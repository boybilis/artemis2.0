@extends('admin.layouts.app')
@section('title', 'Test Bank Enrollments')
@section('content')
<a class="btn-ghost" href="{{ route('admin.test-banks.index') }}">← Test Banks</a>
<section class="panel" style="margin-top:20px"><h2 class="panel-title">{{ $testBank->title }} — Enrollments</h2>
<div style="overflow:auto"><table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th>Learner</th><th>Exam / location</th><th>Country</th><th>Status</th><th>Enrolled</th><th>Expires</th></tr></thead><tbody>
@forelse($enrollments as $enrollment)
<tr><td style="padding:16px 8px;border-bottom:1px solid var(--border)">{{ $enrollment->user->name }}<br><small>{{ $enrollment->user->email }}</small></td><td>{{ $enrollment->enrollment_option['name'] ?? 'Standard / previously enrolled' }}</td><td>{{ $enrollment->enrollment_option['country'] ?? '—' }}</td><td>{{ $enrollment->isActive() ? 'Active' : 'Inactive / expired' }}</td><td>{{ $enrollment->enrolled_at?->timezone('Asia/Manila')->format('M j, Y') }}</td><td>{{ $enrollment->expires_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'No expiration' }}</td></tr>
@empty<tr><td colspan="6">No enrollments yet.</td></tr>@endforelse
</tbody></table></div>{{ $enrollments->links() }}</section>
@endsection
