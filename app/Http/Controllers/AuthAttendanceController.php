<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthAttendanceController extends Controller
{
    // 打刻画面の表示
    public function index()
    {
        $user = Auth()->user();
        $formattedDate = now()->format('Y-m-d');
        $formattedTime = now()->format('H:i:s');
        return view('user.attendance-register' , compact('user', 'formattedDate', 'formattedTime'));
    }
}
