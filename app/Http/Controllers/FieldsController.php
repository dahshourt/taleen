<?php

namespace App\Http\Controllers;

use App\Models\fields;

class FieldsController extends Controller
{
    public function all()
    {

        $fields = fields::all();

        return response()->json(['data' => $fields], 200);
    }
}
