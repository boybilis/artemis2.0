<div data-user-progress-tables>
    <p class="muted">{{ $course->title }} · {{ $batch->name }}</p>
    <section class="user-progress-table" aria-labelledby="selected-subject-progress">
        <div class="subject-progress-toolbar">
            <h3 id="selected-subject-progress">Subject Progress</h3>
            @if($subjects->count())
                <label class="subject-progress-switch">
                    <input type="checkbox" role="switch" data-subject-progress-toggle-all aria-controls="subject-progress-rows">
                    <span class="subject-progress-switch-track" aria-hidden="true"></span>
                    <span>Expand all</span>
                </label>
            @endif
        </div>
        <div class="table-wrap">
            <table class="data-table admin-compact-table subject-progress-table">
                <thead><tr><th scope="col">Subject</th><th scope="col">Progress</th></tr></thead>
                <tbody id="subject-progress-rows">
                    @forelse($subjects as $subject)
                        <tr class="{{ $loop->even ? 'subject-progress-alt' : '' }}">
                            <td><div class="subject-progress-name">
                                <button type="button" class="subject-progress-expand" aria-label="Toggle details for {{ $subject['title'] }}" aria-expanded="false" aria-controls="subject-progress-{{ $subject['id'] }}"><span aria-hidden="true">▸</span></button>
                                <span>{{ $subject['title'] }}</span>
                            </div></td>
                            <td><div class="subject-progress-meter">
                                <progress max="100" value="{{ $subject['percentage'] }}" aria-label="{{ $subject['title'] }} progress"></progress>
                                <strong>{{ $subject['percentage'] }}%</strong>
                            </div></td>
                        </tr>
                        <tr id="subject-progress-{{ $subject['id'] }}" class="subject-progress-details {{ $loop->even ? 'subject-progress-alt' : '' }}" hidden>
                            <td colspan="2">
                                <dl>
                                    @foreach(['pre_test' => 'Pre-test', 'post_test' => 'Post-test'] as $type => $label)
                                        <div>
                                            <dt>{{ $label }}</dt>
                                            <dd>
                                                @forelse($subject['results'][$type] as $result)
                                                    <div class="subject-assessment-result">
                                                        <span>{{ $result['title'] }}</span>
                                                        @if($result['attempts']->isEmpty())
                                                            <span class="muted"> · Not taken</span>
                                                        @else
                                                            <ol class="subject-assessment-attempts" reversed>
                                                                @foreach($result['attempts'] as $attempt)
                                                                    <li value="{{ $loop->remaining + 1 }}">
                                                                        Attempt {{ $loop->remaining + 1 }}:
                                                                        {{ $attempt->points_earned ?? $attempt->score }}/{{ $attempt->points_possible ?? $attempt->total }}
                                                                        · {{ $attempt->passed ? 'Passed' : 'Not passed' }}
                                                                    </li>
                                                                @endforeach
                                                            </ol>
                                                        @endif
                                                    </div>
                                                @empty
                                                    Not configured
                                                @endforelse
                                            </dd>
                                        </div>
                                    @endforeach
                                    <div><dt>EXP points</dt><dd>
                                        @if($subject['exp'] === null)
                                            Not enabled
                                        @else
                                            {{ number_format($subject['exp']) }} <small class="muted">· Based on current reward rules</small>
                                        @endif
                                    </dd></div>
                                </dl>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="muted">No subjects are available for this course and batch.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $subjects->links('pagination::bootstrap-4') }}
    </section>

    <section class="user-progress-table" aria-labelledby="selected-final-exams">
        <h3 id="selected-final-exams">Final Exam Attempts</h3>
        <p class="muted">For the selected course and batch.</p>
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
