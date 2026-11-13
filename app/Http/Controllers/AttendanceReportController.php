<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Auth;

class AttendanceReportController extends Controller
{
    // マイ勤怠レポート画面を表示する
    public function index()
    {
        // ログインユーザーの勤怠レポートを集計する
        $report = AttendanceRecord::getReportData(Auth::guard('web')->id());

        // 集計結果をBladeに渡す
        return view('reports.index', $report);
    }
}
