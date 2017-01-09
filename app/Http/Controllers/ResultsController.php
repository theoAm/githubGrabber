<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class ResultsController extends Controller
{
    function index()
    {
        dd($_ENV['GITHUB_ACCESS_TOKEN']);
    }
}
