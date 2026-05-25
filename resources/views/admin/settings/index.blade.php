@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<div class="row g-4">
    <div class="col-md-8">
        <div class="card p-4">
            <h5 class="fw-bold mb-4">General Configuration</h5>
            <form action="#" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Municipal Assembly Name</label>
                    <input type="text" class="form-control" value="Accra Metropolitan Assembly" name="assembly_name">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Default Currency</label>
                        <select class="form-select">
                            <option value="GHS">Ghana Cedi (GH₵)</option>
                            <option value="USD">US Dollar ($)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tax ID / Tin Number</label>
                        <input type="text" class="form-control" value="P001234567X">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Collection Grace Period (Days)</label>
                    <input type="number" class="form-control" value="7">
                    <small class="text-muted">Days allowed for bank deposit before flagging as 'Late Banking'.</small>
                </div>
                <hr>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-4 mb-4">
            <h6 class="fw-bold"><i class="fas fa-bell me-2"></i> Notification Alerts</h6>
            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" checked>
                <label class="form-check-label">SMS on Collection</label>
            </div>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" checked>
                <label class="form-check-label">Email on Fraud Detection</label>
            </div>
        </div>
        <div class="card p-4 border-danger">
            <h6 class="fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Danger Zone</h6>
            <p class="small text-muted">Clear all offline sync logs or reset system sequence.</p>
            <button class="btn btn-sm btn-outline-danger">Reset Transaction Counters</button>
        </div>
    </div>
</div>
@endsection
