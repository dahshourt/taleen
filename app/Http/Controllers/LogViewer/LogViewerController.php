<?php

namespace App\Http\Controllers\LogViewer;

use App\Http\Controllers\Controller;
use App\Services\LogViewer\LogViewerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogViewerController extends Controller
{
    public function __construct(
        private LogViewerService $service
    ) {
        $this->middleware('can:List Log Viewer');
    }

    /**
     * Display the log viewer index page
     */
    public function index(): View
    {
        $logs = $this->service->getFilteredLogs();
        $statistics = $this->service->getStatistics();

        return view('log-viewer.index', compact('logs', 'statistics'));
    }

    /**
     * Display a single log details page
     */
    public function show(int $id): View|RedirectResponse
    {
        $log = $this->service->getLogById($id);

        if (! $log) {
            return redirect()->route('log-viewer.index')->with('error', 'Log not found.');
        }

        return view('log-viewer.show', compact('log'));
    }

    /**
     * Mark a log as resolved
     */
    public function markAsResolved(Request $request, int $id)
    {
        $result = $this->service->resolveLog($id, auth()->id());

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Mark all similar logs as resolved
     */
    public function markAllSimilarAsResolved(Request $request, int $id)
    {
        $result = $this->service->resolveAllSimilar($id, auth()->id());

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Delete a log entry
     */
    public function destroy(int $id)
    {
        $result = $this->service->deleteLog($id);

        if ($result['success']) {
            return redirect()->route('log-viewer.index')->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
