@extends('layouts.app')

@section('title', 'All Collections')

@section('content')
<div class="card p-4 mb-4">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label small fw-bold">Date Range</label>
            <input type="date" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Zone</label>
            <select class="form-select">
                <option>All Zones</option>
                @foreach(\App\Models\Zone::all() as $zone)
                    <option>{{ $zone->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold">Search Receipt/Business</label>
            <input type="text" class="form-control" placeholder="REC-...">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date & Time</th>
                    <th>Receipt #</th>
                    <th>Business</th>
                    <th>Agent</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>GPS Status</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Models\Collection::with('business', 'agent')->latest()->paginate(20) as $item)
                <tr>
                    <td>{{ $item->collected_at->format('M d, Y H:i') }}</td>
                    <td><code>{{ $item->receipt_number }}</code></td>
                    <td>{{ $item->business->name }}</td>
                    <td>{{ $item->agent->name }}</td>
                    <td class="fw-bold">GH₵ {{ number_format($item->amount, 2) }}</td>
                    <td><span class="badge bg-light text-dark">{{ strtoupper($item->payment_method) }}</span></td>
                    <td>
                        @php
                            // Simplified distance check for display
                            $suspicious = \App\Models\SuspiciousActivity::where('type', 'GPS_MISMATCH')->where('related_id', $item->agent_id)->whereDate('created_at', $item->created_at)->exists();
                        @endphp
                        @if($suspicious)
                            <span class="text-danger" title="Mismatched"><i class="fas fa-map-marker-alt"></i> Flagged</span>
                        @else
                            <span class="text-success" title="Verified"><i class="fas fa-check-circle"></i> Valid</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $recon = $item->reconciliation->status ?? 'pending';
                        @endphp
                        <span class="badge {{ $recon == 'verified' ? 'bg-success' : ($recon == 'suspicious' ? 'bg-danger' : 'bg-warning text-dark') }}">
                            {{ strtoupper($recon) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
