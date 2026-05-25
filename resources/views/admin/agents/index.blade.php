@extends('layouts.app')

@section('title', 'Field Agent Management')

@section('content')
<div class="card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5>System Agents</h5>
            <p class="text-muted mb-0">Monitor and manage revenue collectors in various zones.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAgentModal">
            <i class="fas fa-plus me-2"></i> Register New Agent
        </button>
    </div>
</div>

<div class="row g-4">
    @foreach($agents as $agent)
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($agent->name) }}&background=0B6E4F&color=fff" class="rounded-circle me-3" width="50">
                    <div>
                        <h6 class="fw-bold mb-0">{{ $agent->name }}</h6>
                        <span class="badge {{ $agent->is_active ? 'bg-success' : 'bg-danger' }} small">
                            {{ $agent->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <div class="small mb-1 text-muted"><i class="fas fa-envelope me-2"></i> {{ $agent->email }}</div>
                <div class="small mb-1 text-muted"><i class="fas fa-phone me-2"></i> {{ $agent->phone_number }}</div>
                <div class="small mb-3"><i class="fas fa-map-marker-alt me-2 text-success"></i> Assigned Zone: <strong>{{ $agent->zone->name ?? 'Unassigned' }}</strong></div>
                
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary w-100">Performance</button>
                    <button class="btn btn-sm btn-outline-secondary w-100">Edit</button>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Add Agent Modal -->
<div class="modal fade" id="addAgentModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.agents.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">New Field Agent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Assigned Zone</label>
                    <select name="zone_id" class="form-select" required>
                        @foreach($zones as $zone)
                            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Temporary Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Agent</button>
            </div>
        </form>
    </div>
</div>
@endsection
