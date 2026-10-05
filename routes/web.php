<?php

use Illuminate\Support\Facades\Route;

Route::view('/login', 'pages.login')
    ->middleware('guest')
    ->name('login');

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');
    Route::livewire('/playbooks', 'pages::playbooks.index')->name('playbooks.index');
    Route::livewire('/playbooks/{playbook}', 'pages::playbooks.show')->name('playbooks.show');
});
