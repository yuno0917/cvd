<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\PythonController;

Route::get('/', function () {
    return view('book');
});

Route::get('/book', [BookController::class, 'book']);
Route::get('/get-isbn', [BookController::class, 'getIsbn']);
Route::post('/get-book-info', [BookController::class, 'getBookInfo']);
// Route::get('/library', [BookController::class, 'library'])->name('books.library');
Route::post('/verify-2fa', [BookController::class, 'verify2FA'])->name('verify.2fa');
Route::get('/python', [PythonController::class, 'runScript']);

Route::get('/genre', [BookController::class, 'genre'])->name('books.genre');
Route::get('/genre/{genre_id}/book', [BookController::class, 'book'])->name('books.book');
Route::get('/genre/{genre_id}/book/library', [BookController::class, 'library'])->name('books.library');


