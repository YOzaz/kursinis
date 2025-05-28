<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HelpController extends Controller
{
    /**
     * Show the help page.
     */
    public function index()
    {
        return view('help.index');
    }
    
    /**
     * Show the FAQ page.
     */
    public function faq()
    {
        return view('help.faq');
    }
}