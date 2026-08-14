<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/staff/accept-invite', function (\Illuminate\Http\Request $request) {
    return view('staff_redirect', ['token' => $request->query('token')]);
});
