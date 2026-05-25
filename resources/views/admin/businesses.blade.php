@extends('layouts.app')

@section('title', 'Business Registry')

@section('content')
<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5>Registered Businesses</h5>
            <p class="text-muted mb-0">Manage business entities and their levy assignments.</p>
        </div>
        <button class="btn btn-primary"><i class="fas fa-plus me-2"></i> Register New Business</button>
    </div>
</div>

<div class="row g-4">
    @foreach(\App\Models\Business::with('zone')->get() as $b)
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h6 class="fw-bold">{{ $b->name }}</h6>
                    <span class="badge bg-light text-dark">{{ $b->structure_type }}</span>
                </div>
                <div class="small text-muted mb-3"><i class="fas fa-user me-1"></i> {{ $b->owner_name }}</div>
                <div class="small mb-1"><strong>Zone:</strong> {{ $b->zone->name }}</div>
                <div class="small mb-1"><strong>Levy:</strong> {{ $b->levy_type }}</div>
                <div class="h5 mt-3 mb-0 text-success">GH₵ {{ number_format($b->fee_amount, 2) }}</div>
            </div>
            <div class="card-footer bg-white border-0 d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary w-100">Edit</button>
                <button class="btn btn-sm btn-outline-primary w-100">History</button>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
