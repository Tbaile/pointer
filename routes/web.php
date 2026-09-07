<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::dashboard')
    ->middleware('auth')
    ->name('dashboard');

Route::view('/login', 'pages.login')
    ->middleware('guest')
    ->name('login');
