<?php namespace InspireSoftware\MGSSO\Traits;

use InspireSoftware\MGSSO\MGSSOBroker;

trait SSOUser {

    public static function create(array $attributes = []){
        
        $networkUser = MGSSOBroker::createUser($attributes);

        if($networkUser && isset($networkUser['id'])){
            $attributes['network_id'] = $networkUser['id'];
            if(isset($attributes['id'])) unset($attributes['id']);
            return static::query()->create($attributes);
        }

        return null;

    }

}