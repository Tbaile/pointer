<?php

use Illuminate\Support\Facades\Route;

Route::view('/login', 'pages.login')
    ->middleware('guest')
    ->name('login');

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');
    Route::livewire('/diagnoses', 'pages::diagnoses.index')->name('diagnoses.index');
    Route::livewire('/diagnoses/{diagnosis}', 'pages::diagnoses.edit')->name('diagnoses.edit');
});
