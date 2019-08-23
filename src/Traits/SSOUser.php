<?php namespace InspireSoftware\MGSSO\Traits;

use InpireSoftware\MGSSO\MGSSOBroker;

trait SSOUser {

    public static function create(array $attributes = []){
        
        $networkUser = MGSSOBroker::createUser($attributes);

        if($networkUser){
            $attributes['network_id'] = $networkUser['id'];
            if(isset($attributes['id'])) unset($attributes['id']);
            return static::query()->create($attributes);
        }

        return null;

    }

}