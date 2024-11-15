<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\PythonController;

Route::get('/strong', function () {
    return view('cvds.strong');
});

Route::post('/process-image', [BookController::class, 'processImage'])->name('process-image');