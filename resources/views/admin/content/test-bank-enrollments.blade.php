@extends('admin.layouts.app')
@section('title', 'Test Bank Enrollments')
@section('content')
<a class="btn-ghost" href="{{ route('admin.test-banks.index') }}">← Test Banks</a>
<section class="panel bank-enrollment-report" style="margin-top:20px"><h2 class="panel-title">{{ $testBank->title }} — Enrollments</h2>
<form id="enrollmentSearchForm" method="GET" class="enrollment-table-tools">
<label>Show <select name="per_page" aria-label="Enrollments per page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected($enrollments->perPage() === $size)>{{ $size }}</option>@endforeach</select> entries</label>
<label>Search <input type="search" name="search" value="{{ request('search') }}" placeholder="Name, email, licensure or country" aria-label="Search enrollments"></label>
</form>
<div data-ajax-table="bank-enrollments" aria-live="polite">
<div class="enrollment-table-wrap"><table class="enrollment-table"><thead><tr><th>Learner</th><th>Licensure</th><th>Country</th><th>Status</th><th>Enrolled</th><th>Expires</th></tr></thead><tbody>
@forelse($enrollments as $enrollment)
<tr><td style="padding:16px 8px;border-bottom:1px solid var(--border)">{{ $enrollment->user->name }}<br><small>{{ $enrollment->user->email }}</small></td><td>{{ $enrollment->enrollment_option['name'] ?? 'Standard / previously enrolled' }}</td><td>{{ $enrollment->enrollment_option['country'] ?? '—' }}</td><td>{{ $enrollment->isActive() ? 'Active' : 'Inactive / expired' }}</td><td>{{ $enrollment->enrolled_at?->timezone('Asia/Manila')->format('M j, Y') }}</td><td>{{ $enrollment->expires_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'No expiration' }}</td></tr>
@empty<tr><td colspan="6">No matching enrollments.</td></tr>@endforelse
</tbody></table></div>
<div class="enrollment-table-footer"><span>Showing {{ $enrollments->firstItem() ?? 0 }} to {{ $enrollments->lastItem() ?? 0 }} of {{ $enrollments->total() }} entries · Page {{ $enrollments->currentPage() }} of {{ $enrollments->lastPage() }}</span><nav class="pagination" aria-label="Enrollment pages">@if($enrollments->previousPageUrl())<a class="btn-ghost" href="{{ $enrollments->previousPageUrl() }}">Previous</a>@else<button class="btn-ghost" disabled>Previous</button>@endif @if($enrollments->nextPageUrl())<a class="btn-ghost" href="{{ $enrollments->nextPageUrl() }}">Next</a>@else<button class="btn-ghost" disabled>Next</button>@endif</nav></div></div></section>
<style>
.enrollment-table-tools,.enrollment-table-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin:20px 0}
.enrollment-table-tools label{display:flex;align-items:center;gap:8px;font-size:.85rem}
.enrollment-table-tools input,.enrollment-table-tools select{padding:10px 12px;border:1px solid var(--border);border-radius:8px;font:inherit;color:var(--text);background:var(--surface);min-height:40px}
.enrollment-table-tools input{width:min(320px,65vw)}
.enrollment-table-wrap{overflow:auto;border:1px solid var(--border);border-radius:12px}
.enrollment-table{width:100%;border-collapse:collapse;text-align:left;font-size:.9rem}
.enrollment-table th,.enrollment-table td{padding:16px!important;border-bottom:1px solid var(--border)}
.enrollment-table th{font-size:.75rem;text-transform:uppercase;background:#4b5563;color:#fff}
.enrollment-table tbody tr:nth-child(odd) td{background:#fff;color:#073b63}
.enrollment-table tbody tr:nth-child(even) td{background:#f1f3f5;color:#073b63}
.enrollment-table tr:last-child td{border-bottom:0}
.enrollment-table-footer{font-size:.8rem}.enrollment-table-footer nav{display:flex;gap:8px}
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('enrollmentSearchForm');
    let timer;
    const search = () => {
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        loadAdminTable('bank-enrollments', url.href);
    };
    form.addEventListener('submit', event => { event.preventDefault(); clearTimeout(timer); search(); });
    form.elements.search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 350); });
    form.elements.per_page.addEventListener('change', () => { clearTimeout(timer); search(); });
});
</script>
@endsection
