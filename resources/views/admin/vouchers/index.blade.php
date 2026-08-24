@extends('admin.layouts.app')

@section('title', 'Voucher Management')
@section('kicker', 'Payments and Access')



@section('content')
    <section class="panel" data-ajax-table="vouchers-table">
        <p class="panel-label">Voucher list</p>
        <h2 class="panel-title">Exam Access Vouchers</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Course</th><th>Status</th><th>Assigned user</th><th>Amount</th><th>Date created</th></tr></thead>
                <tbody>
                    @forelse ($vouchers as $voucher)
                        <tr>
                            <td><strong>{{ $voucher->code }}</strong></td>
                            <td>{{ $voucher->batch?->course?->title ?? 'No assigned course' }}</td>
                            <td>
                                <span class="status {{ $voucher->used ? 'success' : 'info' }}">
                                    {{ $voucher->used ? 'Redeemed' : 'Active (Unused)' }}
                                </span>
                            </td>
                            <td>
                                @if ($voucher->user)
                                    <strong>{{ $voucher->user->name }}</strong><br>
                                    <span class="muted">{{ $voucher->user->email }}</span>
                                @else
                                    <span class="muted">Unassigned</span>
                                @endif
                            </td>
                            <td>₱{{ number_format($voucher->price, 2) }}</td>
                            <td>{{ $voucher->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No vouchers found in database.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
            {{ $vouchers->links('pagination::bootstrap-4') }}
        </div>
    </section>

<section class="panel" style="margin-top: 18px;" data-ajax-table="redeemed-vouchers-table">
    <p class="panel-label">Redeemed Vouchers</p>
    <h2 class="panel-title">Redemption History</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Receipt</th><th>Course</th><th>Buyer</th><th>Payment Method</th><th>Amount</th><th>Status</th><th>Redeemed Date</th></tr></thead>
            <tbody>
                @forelse ($redeemedVouchers as $idx => $voucher)
                    <tr>
                        <td><code>TXN-2026-{{ str_pad(($redeemedVouchers->firstItem() ?? 1) + $idx, 5, '0', STR_PAD_LEFT) }}</code></td>
                        <td>{{ $voucher->batch?->course?->title ?? 'No assigned course' }}</td>
                        <td>
                            @if ($voucher->user)
                                <strong>{{ $voucher->user->name }}</strong> (<span class="muted">{{ $voucher->user->email }}</span>)
                            @else
                                <span class="muted">Unknown Learner</span>
                            @endif
                        </td>
                        <td><span class="status info">Online Payment</span></td>
                        <td>₱{{ number_format($voucher->price, 2) }}</td>
                        <td><span class="status success">Paid</span></td>
                        <td>{{ $voucher->used_at ? \Carbon\Carbon::parse($voucher->used_at)->format('M d, Y h:i A') : 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No voucher redemption transactions recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
        {{ $redeemedVouchers->links('pagination::bootstrap-4') }}
    </div>
</section>
@endsection
