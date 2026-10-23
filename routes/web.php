<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;

Route::get('/login', fn () => redirect('/admin/login'))->name('login');
Route::get('/struk/{id}', [ReceiptController::class, 'show'])->middleware('auth')->name('struk');

Route::get('/', function () {
    return view('welcome');
});
