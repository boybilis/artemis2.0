@extends('admin.layouts.app')

@section('title', 'Reports')
@section('kicker', 'Analytics')

@section('header_actions')
    <button class="btn btn-muted" type="button">Export CSV</button>
    <button class="btn btn-muted" type="button">Export PDF</button>
@endsection

@section('content')
<div class="page-grid three">
    <article class="panel">
        <p class="panel-label">User completion</p>
        <h2 class="panel-title">Completion Report</h2>
        <p class="panel-subtitle"><strong>{{ $completedStudents }} certified</strong> out of <strong>{{ $startedStudents }} learners</strong> registered in database.</p>
    </article>
    <article class="panel">
        <p class="panel-label">Exam pass/fail</p>
        <h2 class="panel-title">Exam Report</h2>
        @php
            $examPassRate = $totalExams > 0 ? round(($passedExams / $totalExams) * 100) : 0;
        @endphp
        <p class="panel-subtitle"><strong>{{ $examPassRate }}% pass rate</strong> ({{ $passedExams }} passed out of {{ $totalExams }} attempts).</p>
    </article>
    <article class="panel">
        <p class="panel-label">Voucher sales</p>
        <h2 class="panel-title">Sales Report</h2>
        <p class="panel-subtitle"><strong>₱{{ number_format($totalRevenue, 2) }}</strong> total revenue across <strong>{{ $totalSales }} vouchers</strong> sold.</p>
    </article>
</div>

<section class="panel" style="margin-top: 18px;">
    <div class="toolbar">
        <div>
            <p class="panel-label">Summary</p>
            <h2 class="panel-title">System Metrics Summary</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Report category</th><th>Key Metric Value</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td><strong>User completion rate</strong></td><td>{{ $completedStudents }} / {{ $startedStudents }} certified learners</td><td><span class="status success">Active</span></td></tr>
                <tr><td><strong>Exam success rate</strong></td><td>{{ $passedExams }} passed, {{ $failedExams }} failed exam attempts</td><td><span class="status success">Active</span></td></tr>
                <tr><td><strong>Total revenue generated</strong></td><td>₱{{ number_format($totalRevenue, 2) }} PHP</td><td><span class="status success">Active</span></td></tr>
                <tr><td><strong>Prepaid vouchers sold</strong></td><td>{{ $totalSales }} active or redeemed keys</td><td><span class="status success">Active</span></td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection
