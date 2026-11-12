<div data-user-progress-tables>
    <p class="muted">{{ $subject->title }} · {{ $batch->name }}</p>
    <section class="user-progress-table" aria-labelledby="selected-topic-completion">
        <h3 id="selected-topic-completion">Topic Completion</h3>
        <div class="table-wrap">
            <table class="data-table admin-compact-table">
                <thead><tr><th scope="col">Topic</th><th scope="col">Status</th><th scope="col">Best quiz score</th></tr></thead>
                <tbody>
                    @forelse($topics as $topic)
                        @php($attempt = $bestAttempts->get($topic->id)?->first())
                        <tr>
                            <td>{{ $topic->title }}</td>
                            <td><span class="status {{ $completedIds->has($topic->id) ? 'success' : 'neutral' }}">{{ $completedIds->has($topic->id) ? 'Completed' : 'Locked/In progress' }}</span></td>
                            <td>{{ $attempt ? $attempt->score.'/'.$attempt->total : 'No attempt' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">No topics are available for this subject.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $topics->links('pagination::bootstrap-4') }}
    </section>

    <section class="user-progress-table" aria-labelledby="selected-final-exams">
        <h3 id="selected-final-exams">Final Exam Attempts</h3>
        <p class="muted">For this subject’s course and batch.</p>
        <div class="table-wrap">
            <table class="data-table admin-compact-table">
                <thead><tr><th scope="col">Date</th><th scope="col">Score</th><th scope="col">Result</th></tr></thead>
                <tbody>
                    @forelse($examAttempts as $attempt)
                        <tr>
                            <td>{{ $attempt->created_at->format('M d, Y h:i A') }}</td>
                            <td>{{ $attempt->score }}/{{ $attempt->total }}</td>
                            <td><span class="status {{ $attempt->passed ? 'success' : 'danger' }}">{{ $attempt->passed ? 'Passed' : 'Failed' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">No final exam attempts recorded for this course and batch.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $examAttempts->links('pagination::bootstrap-4') }}
    </section>

    <section class="user-progress-table" aria-labelledby="selected-vouchers">
        <h3 id="selected-vouchers">Vouchers Purchased</h3>
        <p class="muted">For this batch.</p>
        <div class="table-wrap">
            <table class="data-table admin-compact-table">
                <thead><tr><th scope="col">Code</th><th scope="col">Price</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    @forelse($vouchers as $voucher)
                        <tr>
                            <td><code>{{ $voucher->code }}</code></td>
                            <td>₱{{ number_format($voucher->price, 2) }}</td>
                            <td><span class="status {{ $voucher->statusCssClass() }}">{{ $voucher->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="muted">No vouchers recorded for this batch.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $vouchers->links('pagination::bootstrap-4') }}
    </section>
</div>
