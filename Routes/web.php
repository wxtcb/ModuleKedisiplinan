<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;

Route::prefix('kedisiplinan')->group(function() {
    Route::prefix('alpha')->group(function() {
        Route::get('/', 'AlphaController@index'); 
    });

    Route::prefix('disiplin')->group(function() {
        Route::get('/', 'DisiplinController@index'); 
    });
});
