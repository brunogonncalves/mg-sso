<?php

Route::group(['middleware' => ['web']], function () {
    Route::post('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@login');
    Route::post('password/email', 'InspireSoftware\MGSSO\Controllers\MGSSOController@sendResetLinkEmail')->name('password.email');
    Route::get('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@index')->middleware('guest')->name('login');
    Route::any('logout', 'InspireSoftware\MGSSO\Controllers\MGSSOController@logout')->name('logout');
    Route::get('user/verify/{token}', 'InspireSoftware\MGSSO\Controllers\MGSSOController@verifyUser');
    Route::name('send.token')->post('send-token','InspireSoftware\MGSSO\Controllers\MGSSOController@sendToken');
    Route::name('alter.password.user')->post('alter-password-user','InspireSoftware\MGSSO\Controllers\MGSSOController@changePassword');
    Route::name('check.email')->get('check-email/{email}','InspireSoftware\MGSSO\Controllers\MGSSOController@checkEmail');
    Route::post('language', 'InspireSoftware\MGSSO\Controllers\MGSSOController@setLanguage');
});