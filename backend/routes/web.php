<?php

// The backend is API-first; this route retains Laravel's basic landing response.
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
