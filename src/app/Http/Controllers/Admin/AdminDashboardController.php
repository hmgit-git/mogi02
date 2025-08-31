<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $attendances = Attendance::with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('clock_in_at')
            ->limit(100)
            ->get();

        return view('admin.dashboard', compact('attendances'));
    }
}
