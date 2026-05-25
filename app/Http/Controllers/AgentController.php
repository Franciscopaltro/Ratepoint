<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AgentController extends Controller
{
    public function index()
    {
        $agents = User::where('role', 'field_agent')->with('zone')->get();
        $zones = Zone::all();
        return view('admin.agents.index', compact('agents', 'zones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone_number' => 'required',
            'zone_id' => 'required|exists:zones,id',
            'password' => 'required|min:8',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'zone_id' => $request->zone_id,
            'role' => 'field_agent',
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Field Agent added successfully!');
    }

    public function update(Request $request, User $agent)
    {
        $agent->update($request->only(['name', 'email', 'phone_number', 'zone_id', 'is_active']));
        return redirect()->back()->with('success', 'Agent details updated!');
    }
}
