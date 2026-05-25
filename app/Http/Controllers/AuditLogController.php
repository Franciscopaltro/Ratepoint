<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = \App\Models\AuditLog::with('user')->latest()->paginate(50);
        return view('admin.audit_logs.index', compact('logs'));
    }
}
