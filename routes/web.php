<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\PythonController;


Route::get('/', function () {
    return view('book');
});

// ルート定義の整理
Route::get('/judge', [BookController::class, 'showJudge'])->name('judge');
Route::post('/judge/result', [BookController::class, 'judgeResult'])->name('judge.result');
Route::get('/measure', [BookController::class, 'showMeasure'])->name('measure');
Route::post('/measure/result', [BookController::class, 'measureResult'])->name('measure.result');
Route::get('/strong', [BookController::class, 'showStrong'])->name('strong');
Route::get('/weak', [BookController::class, 'showWeak'])->name('weak');

// 画像処理関連のルート
Route::post('/process-image', [BookController::class, 'processImage'])->name('process-image');
Route::post('/process-weak-image', [BookController::class, 'processWeakImage'])->name('process-weak-image');
