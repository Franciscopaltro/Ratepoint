@extends('layouts.app')

@section('title', 'Financial Reconciliation')

@section('content')
<div class="card p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h5>Three-Way Match Verification</h5>
            <p class="text-muted small">Compare Agent Collection, Finance Confirmation, and Bank Deposit Records. All three must match to verify.</p>
        </div>
        <div class="col-md-4 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadBankSlipModal">
                <i class="fas fa-upload me-2"></i> Bulk Bank Slip Upload
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Receipt #</th>
                    <th>Business</th>
                    <th>Agent</th>
                    <th>Amount (Agent)</th>
                    <th>Status</th>
                    <th>Match Logic</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pending as $item)
                <tr>
                    <td><code>{{ $item->receipt_number }}</code></td>
                    <td>{{ $item->business->name }}</td>
                    <td>{{ $item->agent->name }}</td>
                    <td class="fw-bold">GH₵ {{ number_format($item->amount, 2) }}</td>
                    <td>
                        @php
                            $status = $item->reconciliation->status ?? 'pending';
                            $badgeClass = $status == 'verified' ? 'bg-success' : ($status == 'suspicious' ? 'bg-danger' : 'bg-warning text-dark');
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ strtoupper($status) }}</span>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <i class="fas fa-check-circle text-success" title="Agent Record Exist"></i>
                            <i class="fas fa-circle {{ isset($item->reconciliation->confirmed_amount) ? 'text-success' : 'text-muted' }}" title="Finance Match"></i>
                            <i class="fas fa-circle {{ isset($item->reconciliation->bank_slip_number) ? 'text-success' : 'text-muted' }}" title="Bank Record"></i>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="openReconcileModal({{ $item->id }}, '{{ $item->receipt_number }}', {{ $item->amount }})">
                            Verify Match
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-3">
        {{ $pending->links() }}
    </div>
</div>

<!-- Reconcile Modal -->
<div class="modal fade" id="reconcileModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('admin.reconciliation.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="collection_id" id="modal_collection_id">
            <div class="modal-header">
                <h5 class="modal-title">Verify Collection: <span id="modal_receipt_number"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small">
                    Agent reported amount: <strong>GH₵ <span id="modal_agent_amount"></span></strong>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmed Cash Received</label>
                    <div class="input-group">
                        <span class="input-group-text">GH₵</span>
                        <input type="number" step="0.01" name="confirmed_amount" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank Deposit Slip Number</label>
                    <input type="text" name="bank_slip_number" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bank Deposit Date</label>
                    <input type="date" name="bank_deposit_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Officer Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Verify</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReconcileModal(id, receipt, amount) {
        document.getElementById('modal_collection_id').value = id;
        document.getElementById('modal_receipt_number').innerText = receipt;
        document.getElementById('modal_agent_amount').innerText = amount.toFixed(2);
        new bootstrap.Modal(document.getElementById('reconcileModal')).show();
    }
</script>
@endsection
