<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/reports/current-vs-previous-month-analysis', 'reports.current-vs-previous-month-analysis');
