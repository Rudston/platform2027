<?php

use App\Livewire\Explore\ExploreCommunities;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::livewire('/counter', 'counter');

Route::get('/explore', ExploreCommunities::class)->name('explore');
