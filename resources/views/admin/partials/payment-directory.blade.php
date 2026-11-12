<section class="panel" data-ajax-table="vouchers-table" @if($vouchers === null) data-payment-directory-lazy @endif>
    <p class="panel-label">Payments and access</p>
    <h2 class="panel-title">Batch Enrollment Payments &amp; Codes</h2>
    <form class="payment-table-toolbar" id="voucher-table-filters" method="get" action="{{ route('admin.vouchers.index') }}">
        <div class="field"><label for="voucher-search">Search payments and codes</label><input id="voucher-search" type="search" name="voucher_search" value="{{ $voucherSearch }}" maxlength="120" placeholder="Reference, learner, course, or batch"></div>
        <div class="field"><label for="voucher-size">Rows per page</label><select id="voucher-size" name="voucher_size">@foreach([5,10,25,50] as $size)<option value="{{ $size }}" @selected($voucherPageSize === $size)>{{ $size }}</option>@endforeach</select></div>
        <div><button class="btn-ghost" type="submit">Search</button></div>
    </form>
    <div class="table-wrap payment-table-wrap">
        <table class="data-table admin-compact-table payments-directory-table">
            <thead><tr><th scope="col">Reference</th><th scope="col" class="payment-secondary">Course / Batch</th><th scope="col">Status</th><th scope="col" class="payment-learner">Learner</th><th scope="col" class="payment-secondary">Method</th><th scope="col">Amount</th><th scope="col" class="payment-secondary">Date created</th></tr></thead>
            <tbody>
                @if($vouchers === null)
                <tr><td colspan="7" role="status">Payments will load when this table comes into view.</td></tr>
            @else
            @forelse ($vouchers as $voucher)
                    <tr class="payment-record {{ $loop->even ? 'payment-record-alt' : '' }}">
                            @php($learner = $voucher->assignedLearner ?? $voucher->user)
                            @php($statusLabel = $voucher->used && $voucher->payment_provider !== 'paymongo' ? 'Redeemed / Enrolled' : $voucher->statusLabel())
                            @php($courseTitle = $voucher->batch?->courses?->pluck('title')->join(', ') ?: 'No assigned course')
                        <td><div class="payment-reference">
                            <button type="button" class="payment-expand" aria-expanded="false" aria-controls="payment-detail-{{ $voucher->id }}" aria-label="Toggle details for {{ $voucher->code }}"><span aria-hidden="true">▸</span></button>
                            <strong class="payment-cell">{{ $voucher->code }}</strong>
                        </div></td>
                        <td class="payment-secondary"><span class="payment-cell">{{ $courseTitle }}</span></td>
                        <td><span class="status payment-cell {{ $voucher->statusCssClass() }}">{{ $statusLabel }}</span></td>
                        <td class="payment-learner"><span class="payment-cell">{{ $learner?->name ?? 'Unassigned' }}</span></td>
                        <td class="payment-secondary"><span class="payment-cell">{{ $voucher->paymentMethodLabel() }}</span></td>
                        <td><span class="payment-cell">₱{{ number_format($voucher->price, 2) }}</span></td>
                        <td class="payment-secondary"><span class="payment-cell">{{ $voucher->created_at->format('M d, Y h:i A') }}</span></td>
                    </tr>
                    <tr class="payment-details {{ $loop->even ? 'payment-record-alt' : '' }}" id="payment-detail-{{ $voucher->id }}" hidden>
                        <td colspan="7">
                            <dl>
                                <div><dt>Reference</dt><dd>{{ $voucher->code }}</dd></div>
                                <div><dt>Course / Batch</dt><dd>{{ $courseTitle }}@if($voucher->batch)<br>{{ $voucher->batch->code }} — {{ $voucher->batch->name }}@endif</dd></div>
                                <div><dt>Learner</dt><dd>{{ $learner?->name ?? 'Unassigned' }}@if($learner)<br>{{ $learner->email }}@endif</dd></div>
                                <div><dt>Status</dt><dd>{{ $statusLabel }}</dd></div>
                                <div><dt>Method</dt><dd>{{ $voucher->paymentMethodLabel() }}@if($voucher->payment_provider === 'manual')<br>{{ $voucher->emailed_at ? 'Email sent' : 'Email not confirmed' }}@endif</dd></div>
                                <div><dt>Amount</dt><dd>₱{{ number_format($voucher->price, 2) }}</dd></div>
                                <div><dt>Date created</dt><dd>{{ $voucher->created_at->format('M d, Y h:i A') }}</dd></div>
                                @if($voucher->used && ($redeemedAt = $voucher->redeemed_at ?? $voucher->used_at))
                                    <div><dt>Redeemed date</dt><dd>{{ $redeemedAt->format('M d, Y h:i A') }}</dd></div>
                                @endif
                            </dl>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No enrollment payments or access codes found.</td>
                    </tr>
                @endforelse
            @endif
            </tbody>
        </table>
    </div>
    @if($vouchers !== null)
    <div style="padding-top:1.25rem; border-top:1px solid var(--border);">
        <p class="muted" role="status">@if($vouchers->count()) Showing {{ $vouchers->firstItem() }}–{{ $vouchers->lastItem() }} of {{ $vouchers->total() }} records @else No matching records @endif</p>
        @if($vouchers->hasPages())
            <nav class="payment-pages" aria-label="Payment list pagination">
                <button type="button" class="btn-ghost" data-payment-page="{{ $vouchers->previousPageUrl() }}" @disabled($vouchers->onFirstPage())>Previous</button>
                <span>Page {{ $vouchers->currentPage() }} of {{ $vouchers->lastPage() }}</span>
                <button type="button" class="btn-ghost" data-payment-page="{{ $vouchers->nextPageUrl() }}" @disabled(!$vouchers->hasMorePages())>Next</button>
            </nav>
        @endif
    </div>
    @endif
    <p class="payment-list-message muted" role="status" hidden></p>
</section>
