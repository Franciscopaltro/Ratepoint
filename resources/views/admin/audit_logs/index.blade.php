@extends('layouts.app')

@section('title', 'System Audit Logs')

@section('content')
<div class="card p-4 mb-4 bg-dark text-white">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1 text-warning"><i class="fas fa-shield-alt me-2"></i> Immutable Security Logs</h5>
            <p class="text-white-50 small mb-0">Every action performed on the system is tracked with IP addresses and timestamps.</p>
        </div>
        <button class="btn btn-outline-light btn-sm"><i class="fas fa-print me-2"></i> Print for Audit</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 font-monospace small">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td><span class="fw-bold">{{ $log->user->name }}</span> <br> <small class="text-muted">{{ $log->user->role }}</small></td>
                    <td><span class="badge bg-light text-dark border">{{ $log->action }}</span></td>
                    <td style="max-width: 300px;">{{ $log->description }}</td>
                    <td><code>{{ $log->ip_address }}</code></td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No audit logs found.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $logs->links() }}
    </div>
</div>
@endsection
