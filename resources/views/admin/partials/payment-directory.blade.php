<section class="panel" data-ajax-table="vouchers-table" @if($vouchers === null) data-payment-directory-lazy @endif>
    <p class="panel-label">Payments and access</p>
    <h2 class="panel-title">Batch Enrollment Payments &amp; Codes</h2>
    <form class="toolbar form-grid" id="voucher-table-filters" method="get" action="{{ route('admin.vouchers.index') }}">
        @if(request()->filled('redeemed_page'))<input type="hidden" name="redeemed_page" value="{{ request()->integer('redeemed_page') }}">@endif
        <div class="field"><label for="voucher-search">Search payments and codes</label><input id="voucher-search" type="search" name="voucher_search" value="{{ $voucherSearch }}" maxlength="120" placeholder="Reference, learner, course, or batch"></div>
        <div class="field"><label for="voucher-size">Rows per page</label><select id="voucher-size" name="voucher_size">@foreach([5,10,25,50] as $size)<option value="{{ $size }}" @selected($voucherPageSize === $size)>{{ $size }}</option>@endforeach</select></div>
        <div><button class="btn-ghost" type="submit">Search</button></div>
    </form>
    <div class="table-wrap">
        <table class="data-table payments-directory-table">
            <thead><tr><th>Reference</th><th>Course / Batch</th><th>Status</th><th>Learner</th><th>Method</th><th>Amount</th><th>Date created</th></tr></thead>
            <tbody>
                @if($vouchers === null)
                <tr><td colspan="7" role="status">Payments will load when this table comes into view.</td></tr>
            @else
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
