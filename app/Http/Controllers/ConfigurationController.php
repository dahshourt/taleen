<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function index()
    {
        $configs = Configuration::all();

        return view('configurations.index', compact('configs'));
    }

    public function update(Request $request)
    {
        foreach ($request->configurations as $id => $value) {
            Configuration::where('id', $id)->update(['configuration_value' => $value]);
        }

        return redirect()->back()->with('success', 'Configurations updated successfully!');
    }
}
