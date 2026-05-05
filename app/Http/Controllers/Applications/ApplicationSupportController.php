<?php

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;

class ApplicationSupportController extends Controller
{
    public function index()
    {
        $this->authorize('List Applications'); 
        $applications = Application::all();
        $title = 'Application Support';
        return view('applications.support', compact('applications', 'title'));
    }

    public function bulkUpdate(Request $request)
    {
        $this->authorize('List Applications'); 
        
        $appsData = $request->input('apps', []);
        
        foreach ($appsData as $id => $status) {
            $application = Application::find($id);
            if ($application) {
                $application->app_support = $status;
                $application->save();
            }
        }

        return redirect()->back()->with('status', 'Applications updated successfully');
    }
}
