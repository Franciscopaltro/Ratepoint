<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Business;
use App\Models\User;
use App\Models\SuspiciousActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_revenue'          => Collection::sum('amount'),
            'today_revenue'          => Collection::whereDate('collected_at', now())->sum('amount'),
            'total_businesses'       => Business::count(),
            'total_agents'           => User::whereIn('role', ['field_agent', 'agent'])->count(),
            'active_agents'          => User::whereIn('role', ['field_agent', 'agent'])->count(),
            'pending_reconciliations'=> DB::table('reconciliations')->where('status', 'pending')->count(),
            'suspicious_alerts'      => SuspiciousActivity::where('status', 'open')->count(),
        ];

        $revenue_by_zone = DB::table('collections')
            ->join('businesses', 'collections.business_id', '=', 'businesses.id')
            ->join('zones', 'businesses.zone_id', '=', 'zones.id')
            ->select('zones.name', DB::raw('SUM(collections.amount) as total'))
            ->groupBy('zones.name')
            ->get();

        $top_agents = DB::table('collections')
            ->join('users', 'collections.agent_id', '=', 'users.id')
            ->select('users.name', DB::raw('SUM(collections.amount) as total'))
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'revenue_by_zone', 'top_agents'));
    }
}
