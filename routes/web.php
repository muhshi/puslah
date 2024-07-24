<?php

use App\Livewire\Presensi;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::group(['middleware' => 'auth'], function () {
    Route::get('presensi', Presensi::class)->name('presensi');
});
