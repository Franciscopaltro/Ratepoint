<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index');
    }

    public function update(Request $request)
    {
        // Logic for updating system settings (Municipal Name, Currency, etc.)
        return redirect()->back()->with('success', 'System settings updated!');
    }
}
