<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Reconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReconciliationController extends Controller
{
    public function index()
    {
        $pending = Collection::doesntHave('reconciliation')
            ->orWhereHas('reconciliation', function($q) {
                $q->where('status', 'pending');
            })
            ->with('business', 'agent')
            ->paginate(20);

        return view('admin.reconciliation.index', compact('pending'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'collection_id' => 'required|exists:collections,id',
            'confirmed_amount' => 'required|numeric',
            'bank_slip_number' => 'required|string',
            'bank_deposit_date' => 'required|date',
        ]);

        $reconciliation = Reconciliation::updateOrCreate(
            ['collection_id' => $request->collection_id],
            [
                'finance_officer_id' => Auth::id(),
                'confirmed_amount' => $request->confirmed_amount,
                'bank_slip_number' => $request->bank_slip_number,
                'bank_deposit_date' => $request->bank_deposit_date,
                'notes' => $request->notes,
            ]
        );

        $status = $reconciliation->verifyMatch();

        return redirect()->back()->with('success', "Reconciliation processed. Status: " . strtoupper($status));
    }
}
