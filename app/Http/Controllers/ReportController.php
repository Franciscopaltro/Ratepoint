<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->start_date ?? now()->startOfMonth();
        $end_date = $request->end_date ?? now()->endOfMonth();

        $revenue_by_day = Collection::whereBetween('collected_at', [$start_date, $end_date])
            ->select(DB::raw('DATE(collected_at) as date'), DB::raw('SUM(amount) as total'))
            ->groupBy('date')
            ->get();

        $zone_performance = DB::table('collections')
            ->join('businesses', 'collections.business_id', '=', 'businesses.id')
            ->join('zones', 'businesses.zone_id', '=', 'zones.id')
            ->whereBetween('collections.collected_at', [$start_date, $end_date])
            ->select('zones.name', DB::raw('SUM(collections.amount) as total'), DB::raw('COUNT(collections.id) as count'))
            ->groupBy('zones.name')
            ->get();

        return view('admin.reports.index', compact('revenue_by_day', 'zone_performance', 'start_date', 'end_date'));
    }
}
