<section class="panel dashboard-active-batches" data-active-batches data-page-url="{{ $activeBatches->url($activeBatches->currentPage()) }}" aria-labelledby="active-batches-title">
    <h2 id="active-batches-title" class="panel-title" tabindex="-1">Active Batches</h2>
    <p class="panel-subtitle">Currently enrolled students · Updates every 5 minutes</p>
    @php($batchChartMax = max(1, $activeBatches->getCollection()->max('count') ?? 0))
    @if($activeBatches->count())
        <ul class="active-batch-chart" role="list">
            @foreach($activeBatches as $batch)
                <li data-family="{{ $batch['family'] }}">
                    <div class="active-batch-label"><strong>{{ $batch['name'] }}</strong><span>{{ number_format($batch['count']) }}</span></div>
                    @if($batch['courses'])<small>{{ $batch['courses'] }}</small>@endif
                    <div class="active-batch-track" aria-hidden="true"><span style="width:{{ 100 * $batch['count'] / $batchChartMax }}%"></span></div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="muted">No active batches to display.</p>
    @endif
    @if($activeBatches->hasPages())
        <nav class="recent-users-pagination" aria-label="Active batches pagination">
            <button type="button" class="btn-ghost" data-active-batches-page="{{ $activeBatches->previousPageUrl() }}" @disabled($activeBatches->onFirstPage())>Previous</button>
            <span>Page {{ $activeBatches->currentPage() }} of {{ $activeBatches->lastPage() }}</span>
            <button type="button" class="btn-ghost" data-active-batches-page="{{ $activeBatches->nextPageUrl() }}" @disabled(!$activeBatches->hasMorePages())>Next</button>
        </nav>
    @endif
    <p data-active-batches-error class="muted" role="status" hidden></p>
</section>
