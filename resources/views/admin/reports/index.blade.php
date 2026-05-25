@extends('layouts.app')

@section('title', 'Financial Reports & Analytics')

@section('content')
<div class="card p-4 mb-4">
    <form action="{{ route('admin.reports.index') }}" method="GET" class="row g-3">
        <div class="col-md-4">
            <label class="form-label small fw-bold">From Date</label>
            <input type="date" name="start_date" class="form-control" value="{{ \Carbon\Carbon::parse($start_date)->format('Y-m-d') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold">To Date</label>
            <input type="date" name="end_date" class="form-control" value="{{ \Carbon\Carbon::parse($end_date)->format('Y-m-d') }}">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Filter Report</button>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-success w-100"><i class="fas fa-file-excel me-2"></i> Export</button>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card p-4">
            <h6 class="fw-bold mb-4">Daily Revenue Trend</h6>
            <canvas id="dailyTrendChart" height="250"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-4">
            <h6 class="fw-bold mb-4">Collection Volume by Zone</h6>
            <canvas id="zoneVolumeChart" height="250"></canvas>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold">Zone Performance Breakdown</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Zone Name</th>
                    <th>Transactions</th>
                    <th>Total Collected</th>
                    <th>Target Achievement</th>
                    <th>Growth</th>
                </tr>
            </thead>
            <tbody>
                @foreach($zone_performance as $zone)
                <tr>
                    <td class="fw-bold">{{ $zone->name }}</td>
                    <td>{{ $zone->count }}</td>
                    <td class="text-success fw-bold">GH₵ {{ number_format($zone->total, 2) }}</td>
                    <td>
                        <div class="progress" style="height: 8px; width: 100px;">
                            <div class="progress-bar bg-success" style="width: 75%"></div>
                        </div>
                    </td>
                    <td class="text-success"><i class="fas fa-caret-up"></i> 4.2%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const trendCtx = document.getElementById('dailyTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($revenue_by_day->pluck('date')) !!},
            datasets: [{
                label: 'Revenue',
                data: {!! json_encode($revenue_by_day->pluck('total')) !!},
                borderColor: '#1B3022',
                backgroundColor: 'rgba(27, 48, 34, 0.1)',
                fill: true,
                tension: 0.4
            }]
        }
    });

    const volumeCtx = document.getElementById('zoneVolumeChart').getContext('2d');
    new Chart(volumeCtx, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($zone_performance->pluck('name')) !!},
            datasets: [{
                data: {!! json_encode($zone_performance->pluck('count')) !!},
                backgroundColor: ['#1B3022', '#F4B400', '#1F2937', '#16A34A', '#D93025']
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>
@endsection
