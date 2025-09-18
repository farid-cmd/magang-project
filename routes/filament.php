<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Auth\Login;

Route::post('/admin/login', [Login::class, 'authenticate'])
    ->name('filament.admin.auth.login.post');
