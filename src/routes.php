<?php

Route::post('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@login');
Route::get('mgsso/verify-user', function(){
    return dd(Auth::user());
});