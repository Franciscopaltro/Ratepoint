@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{-- ── Page Header ── --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-700" style="font-weight:700; color:#1a2e22;">Dashboard</h4>
        <p class="mb-0" style="font-size:0.8rem; color:#6c7a6f;">Welcome back, {{ Auth::user()->name ?? 'Admin' }}</p>
    </div>
    <div style="font-size:0.8rem; color:#6c7a6f;">
        <i class="fas fa-calendar-alt me-1"></i>{{ now()->format('D, d M Y') }}
    </div>
</div>

{{-- ── Stat Cards ── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card border-green">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">GH₵ {{ number_format($stats['total_revenue'], 2) }}</div>
            <div class="stat-sub"><i class="fas fa-arrow-up text-success me-1" style="font-size:0.65rem;"></i>All time collections</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-yellow">
            <div class="stat-label">Today</div>
            <div class="stat-value">GH₵ {{ number_format($stats['today_revenue'], 2) }}</div>
            <div class="stat-sub">Real-time updates</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-blue">
            <div class="stat-label">Agents</div>
            <div class="stat-value">{{ $stats['total_agents'] ?? \App\Models\User::where('role', 'agent')->count() }}</div>
            <div class="stat-sub">Active field agents</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card border-red">
            <div class="stat-label">Pending Recon</div>
            <div class="stat-value">{{ $stats['pending_reconciliations'] }}</div>
            <div class="stat-sub">Unverified bank slips</div>
        </div>
    </div>
</div>

{{-- ── Charts Row ── --}}
<div class="row g-3 mb-4">
    {{-- Revenue Chart --}}
    <div class="col-md-8">
        <div class="content-card">
            <div class="card-heading">Revenue Analytics</div>
            <canvas id="revenueChart" height="220"></canvas>
        </div>
    </div>

    {{-- Top Agents --}}
    <div class="col-md-4">
        <div class="content-card h-100">
            <div class="card-heading">Top Agents</div>
            @forelse($top_agents as $agent)
            <div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid #f0f5f2;">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:34px; height:34px; border-radius:50%; background:#d1fae5; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-user" style="font-size:0.75rem; color:#22a96e;"></i>
                    </div>
                    <div>
                        <div style="font-size:0.82rem; font-weight:600; color:#1a2e22;">{{ $agent->name }}</div>
                        <div style="font-size:0.7rem; color:#6c7a6f;">Field Agent</div>
                    </div>
                </div>
                <div style="font-size:0.82rem; font-weight:700; color:#22a96e;">GH₵ {{ number_format($agent->total, 2) }}</div>
            </div>
            @empty
            <div class="text-center py-4" style="color:#6c7a6f; font-size:0.85rem;">
                <i class="fas fa-users mb-2" style="font-size:1.5rem; opacity:0.3;"></i>
                <p class="mb-0">No agent data yet</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Suspicious Activities ── --}}
<div class="row g-3">
    <div class="col-12">
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="card-heading mb-0">Recent Suspicious Activities</div>
                <span style="background:#fee2e2; color:#991b1b; padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:700;">
                    <i class="fas fa-shield-alt me-1"></i>Fraud Detection Active
                </span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(\App\Models\SuspiciousActivity::latest()->limit(5)->get() as $alert)
                        <tr>
                            <td><span style="background:#f0f5f2; color:#1a2e22; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:600;">{{ $alert->type }}</span></td>
                            <td style="font-size:0.82rem;">{{ $alert->description }}</td>
                            <td>
                                @if($alert->severity == 'high')
                                    <span class="badge-danger-soft"><i class="fas fa-exclamation-triangle me-1"></i>High</span>
                                @else
                                    <span class="badge-warning-soft">Medium</span>
                                @endif
                            </td>
                            <td style="font-size:0.8rem; color:#6c7a6f;">{{ $alert->created_at->diffForHumans() }}</td>
                            <td><span class="badge-warning-soft">{{ $alert->status }}</span></td>
                            <td>
                                <button class="btn btn-sm" style="background:#fee2e2; color:#991b1b; border:none; border-radius:6px; font-size:0.75rem; padding:4px 12px; font-weight:600;">
                                    Investigate
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:#6c7a6f; font-size:0.85rem;">
                                <i class="fas fa-check-circle me-2" style="color:#22a96e;"></i>No suspicious activities detected.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($revenue_by_zone->pluck('name')) !!},
            datasets: [{
                label: 'Revenue',
                data: {!! json_encode($revenue_by_zone->pluck('total')) !!},
                backgroundColor: '#22a96e',
                borderRadius: 5,
                borderSkipped: false,
                barPercentage: 0.55,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 14,
                        font: { size: 11, family: 'Inter' },
                        color: '#6c7a6f'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ' GH₵ ' + ctx.parsed.y.toLocaleString()
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, family: 'Inter' }, color: '#6c7a6f' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0f5f2', drawBorder: false },
                    ticks: {
                        font: { size: 11, family: 'Inter' },
                        color: '#6c7a6f',
                        callback: val => val.toLocaleString()
                    }
                }
            }
        }
    });
</script>
@endsection
