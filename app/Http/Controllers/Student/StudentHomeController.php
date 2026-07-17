<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StudentHomeController extends Controller
{
    public function index(): View
    {
        return view('student.home');
    }
}