<?php

Route::post('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@login');
Route::any('logout', 'InspireSoftware\MGSSO\Controllers\MGSSOController@logout');
Route::get('mgsso/verify-user', function(){
    return dd(Auth::user());
});