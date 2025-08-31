<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $users = User::where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name', 'email']);
        return view('admin.staff.index', compact('users'));
    }

    public function show(User $user)
    {
        return view('admin.staff.show', compact('user'));
    }
}
