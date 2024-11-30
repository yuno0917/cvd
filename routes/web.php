<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\PythonController;


Route::get('/', function () {
    return view('book');
});

Route::get('/judge', function () {
    return view('cvds.judge');
});


Route::get('/measure', function () {
    return view('cvds.measure');
});

Route::get('/strong', function () {
    return view('cvds.strong');
});

Route::get('/weak', function () {
    return view('cvds.weak');
});


Route::post('/process-image', [BookController::class, 'processImage'])->name('process-image');
Route::post('/process-weak-image', [BookController::class, 'processWeakImage'])->name('process-weak-image'); 