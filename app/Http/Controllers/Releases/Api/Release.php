<?php

namespace App\Http\Controllers\Releases\Api;

use App\Factories\Releases\ReleaseFactory;
use App\Http\Controllers\Controller;
use App\Http\Resources\releaseResource;
use Illuminate\Http\Request;

class Release extends Controller
{
    private $release;

    public function __construct(ReleaseFactory $release)
    {

        $this->release = $release::index();

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $list_release = $this->release->list();

        return response()->json(['data' => $list_release], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $add_release = $this->release->create($request);

        return response()->json(['data' => 'Added Successfully'], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $release = $this->release->show($id);

        // $release = releaseResource::collection($release);
        return response()->json(['data' => $release], 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Request $request) {}

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id, Request $request)
    {

        $release = $this->release->update($id, $request->all());
        if ($release) {
            return response()->json(['data' => 'Updated'], 200);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function lisVendor()
    {
        $list_vendo = $this->release->listVendor();

        return response()->json(['data' => $list_vendo], 200);
    }

    public function lisReleaseStatus()
    {
        $listReleaseStatus = $this->release->listStatus();

        return response()->json(['data' => $listReleaseStatus], 200);
    }
}
