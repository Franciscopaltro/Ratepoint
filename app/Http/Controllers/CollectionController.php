<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Zone;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        $query = Collection::with('business', 'agent', 'reconciliation');

        if ($request->zone) {
            $query->whereHas('business', function($q) use ($request) {
                $q->whereHas('zone', function($z) use ($request) {
                    $z->where('name', $request->zone);
                });
            });
        }

        if ($request->search) {
            $query->where('receipt_number', 'like', "%{$request->search}%")
                  ->orWhereHas('business', function($q) use ($request) {
                      $q->where('name', 'like', "%{$request->search}%");
                  });
        }

        $collections = $query->latest()->paginate(20);
        return view('admin.collections.index', compact('collections'));
    }
}
