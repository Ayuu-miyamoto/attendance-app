<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StampCorrection;

class StampCorrectionController extends Controller
{
    public function index()
    {
        $applications = StampCorrection::all();
        return view('admin.admin-application-list', compact('applications'));
    }
}
