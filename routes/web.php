<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return view('welcome');
});

// Login untuk Filament (kalau memang mau override Filament login)
Route::post('/admin/login', [LoginController::class, 'authenticate'])
    ->name('filament.admin.auth.login.post');

// Login umum via SSO
Route::post('/login', [LoginController::class, 'authenticate'])
    ->name('login');

// Logout
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');