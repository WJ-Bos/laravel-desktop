<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

require __DIR__.'/desktop.php';
require __DIR__.'/settings.php';
