<?php

Route::get('mgsso', function(){
    // Auth::loginUsingId(1);
    return dd(Auth::user());
});

Route::post('login', 'InspireSoftware\MGSSO\Controllers\MGSSOController@login');