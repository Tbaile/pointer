<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');

Route::view('/login', 'pages.login')
    ->middleware('guest')
    ->name('login');
