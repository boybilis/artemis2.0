@extends('admin.layouts.app')

@section('title', Auth::user()->hasAdminAccess() ? 'Payment and Vouchers' : 'Voucher Management')
@section('kicker', 'Payments and Access')



@section('content')
@if(Auth::user()->hasAdminAccess())
    <section class="panel" style="margin-bottom:1rem">
        <h2 class="panel-title">Automatic Payments</h2>
        <p class="panel-subtitle">Pause new PayMongo checkouts for maintenance. Existing payments can still be verified, and enrollment codes remain usable.</p>
        <form id="payment-mode-form" method="post" action="{{ route('admin.vouchers.payment-mode') }}" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            @csrf @method('PUT')
            <label><input type="checkbox" id="automatic-payment-toggle" role="switch" @checked($automaticPayments)> Enable automatic PayMongo payments</label>
            <button type="submit" class="btn-primary">Save payment mode</button>
        </form>
    </section>
    <section class="panel" style="margin-bottom:1rem">
        <h2 class="panel-title">Issue Enrollment Voucher</h2>
        <p class="panel-subtitle">Generate a single-use code for the learner’s registered email and selected batch.</p>
        <form id="assigned-voucher-form" action="{{ route('admin.vouchers.assign') }}" method="post">
            @csrf
            <div class="form-grid">
                <div class="field"><label for="voucher-learner-email">Learner email</label><input id="voucher-learner-email" type="email" name="email" required maxlength="255"></div>
                <div class="field"><label for="voucher-batch">Batch class</label><select id="voucher-batch" name="batch_id" required><option value="">Select an available batch</option>@foreach($paymentBatches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }} ({{ $batch->code }})</option>@endforeach</select></div>
            </div>
            <button class="btn-primary" type="submit" @disabled($paymentBatches->isEmpty())>Generate &amp; Email Voucher</button>
        </form>
    </section>
@endif
    <section class="panel" data-ajax-table="vouchers-table">
        <p class="panel-label">Payments and access</p>
        <h2 class="panel-title">Batch Enrollment Payments &amp; Codes</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Reference</th><th>Course / Batch</th><th>Status</th><th>Learner</th><th>Method</th><th>Amount</th><th>Date created</th></tr></thead>
                <tbody>
                    @forelse ($vouchers as $voucher)
                        <tr>
                            <td><strong>{{ $voucher->code }}</strong></td>
                            <td><strong>{{ $voucher->batch?->courses?->pluck('title')->join(', ') ?: 'No assigned course' }}</strong>@if($voucher->batch)<br><span class="muted">{{ $voucher->batch->code }} — {{ $voucher->batch->name }}</span>@endif</td>
                            <td>
                                <span class="status {{ $voucher->statusCssClass() }}">
                                    {{ $voucher->statusLabel() }}
                                </span>
                            </td>
                            <td>
                                @php($learner = $voucher->assignedLearner ?? $voucher->user)
                                @if ($learner)
                                    <strong>{{ $learner->name }}</strong><br>
                                    <span class="muted">{{ $learner->email }}</span>
                                @else
                                    <span class="muted">Unassigned</span>
                                @endif
                            </td>
                            <td><span class="status info">{{ $voucher->paymentMethodLabel() }}</span>@if($voucher->payment_provider === 'manual')<br><small>{{ $voucher->emailed_at ? 'Email sent' : 'Email not confirmed' }}</small>@endif</td>
                            <td>₱{{ number_format($voucher->price, 2) }}</td>
                            <td>{{ $voucher->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="muted">No enrollment payments or access codes found.</td>
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
                        <td>{{ $voucher->batch?->courses?->pluck('title')->join(', ') ?: 'No assigned course' }}</td>
                        <td>
                            @if ($voucher->user)
                                <strong>{{ $voucher->user->name }}</strong> (<span class="muted">{{ $voucher->user->email }}</span>)
                            @else
                                <span class="muted">Unknown Learner</span>
                            @endif
                        </td>
                        <td><span class="status info">{{ $voucher->paymentMethodLabel() }}</span></td>
                        <td>₱{{ number_format($voucher->price, 2) }}</td>
                        <td><span class="status success">{{ $voucher->statusLabel() }}</span></td>
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
@if(Auth::user()->hasAdminAccess())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mode = document.getElementById('payment-mode-form');
    const issue = document.getElementById('assigned-voucher-form');
    for (const form of [mode, issue]) form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type=submit]');
        if (button.disabled) return;
        button.disabled = true;
        const payload = new FormData(form);
        if (form === mode) payload.set('enabled', document.getElementById('automatic-payment-toggle').checked ? '1' : '0');
        try {
            const response = await fetch(form.action, {method:'POST', body:payload, headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json().catch(() => null);
            if (!response.ok || !data?.success) throw new Error(Object.values(data?.errors || {}).flat()[0] || 'Unable to save. Please try again.');
            showAdminToast(data.message, data.emailed === false ? 'info' : 'success');
            if (form === issue) {
                form.reset();
                await loadAdminTable('vouchers-table', @json(route('admin.vouchers.index')), false);
            }
        } catch (error) { showAdminToast(error.message, 'error'); }
        finally { button.disabled = false; }
    });
});
</script>
@endif
@endsection
