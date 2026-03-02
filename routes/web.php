<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;

Route::get('/', function () {
    return response()->json(['laravel' => app()->version()]);
});
