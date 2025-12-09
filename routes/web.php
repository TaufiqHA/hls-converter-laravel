<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmbedController;

Route::get('/', function () {
    return view('welcome');
});

// Embed route for displaying embedded video player
Route::get('/embed/{id}', [EmbedController::class, 'showEmbed']);
