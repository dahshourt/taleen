<?php

namespace App\Http\Controllers\Logs\Api;

use App\Factories\Logs\LogFactory;
// use App\Http\Requests\Users\Api\LogRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\LogResource;

class LogController extends Controller
{
    // use ValidatesRequests;
    private $log;

    public function __construct(LogFactory $log)
    {

        $this->log = $log::index();

    }

    public function index()
    {
        $logs = $this->log->getAll();

        return response()->json(['data' => $logs], 200);
    }

    //
    public function show($id)
    {
        $log = $this->log->get_by_cr_id($id);
        if (! $log) {
            return response()->json([
                'message' => 'log Not Exists',
            ], 422);
        }
        $log = LogResource::collection($log);

        return response()->json(['data' => $log], 200);
    }
}
