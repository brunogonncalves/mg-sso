<?php

Route::group(['middleware' => ['web']], function () {
    Route::post('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@login');
    Route::post('password/email', 'InspireSoftware\MGSSO\Controllers\MGSSOController@sendResetLinkEmail')->name('password.email');
    Route::get('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@index')->name('login');
    Route::any('logout', 'InspireSoftware\MGSSO\Controllers\MGSSOController@logout');
    Route::get('mgsso/verify-user', function(){
        return dd(Auth::user());
    });
});