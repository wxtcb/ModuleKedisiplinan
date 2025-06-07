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

Route::prefix('kedisiplinan')->group(function () {
    Route::prefix('alpha')->group(function () {
        Route::get('/', 'AlphaController@index')->name('alpha.index');
        Route::get('/show/{id}', 'AlphaController@show')->name('alpha.show');
        Route::get('/alpha/{id}/export-excel', 'AlphaController@exportExcel')->name('alpha.export');
        Route::get('/sanksi/{id}', 'AlphaController@sanksi')->name('alpha.sanksi');
        Route::get('/create/{id}', 'AlphaController@create')->name('alpha.create');
        Route::post('/store', 'AlphaController@store')->name('alpha.store');
        Route::post('/alpha/hitung-tidak-hadir', 'AlphaController@hitungTidakHadir')->name('alpha.hitung_tidak_hadir');
    });

    Route::prefix('disiplin')->group(function () {
        Route::get('/', 'DisiplinController@index')->name('disiplin.index');
        Route::get('/show/{id}', 'DisiplinController@show')->name('disiplin.show');
        Route::get('/export/{id}', 'DisiplinController@export')->name('disiplin.export');
    });
});
