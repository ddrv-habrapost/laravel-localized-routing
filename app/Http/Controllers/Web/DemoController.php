<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class DemoController extends Controller
{

    public function home()
    {
        return view('home');
    }

    public function about()
    {
        return view('about');
    }

    public function contacts()
    {
        return view('contacts');
    }

    public function news()
    {
        return view('news');
    }
}
