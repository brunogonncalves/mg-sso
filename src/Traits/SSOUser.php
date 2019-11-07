<?php namespace InspireSoftware\MGSSO\Traits;

use InspireSoftware\MGSSO\MGSSOBroker;

trait SSOUser {

    public static function create(array $attributes = []){
        $broker = new MGSSOBroker;
        $networkUser = $broker->create($attributes);

        if($networkUser && isset($networkUser['id'])){
            $attributes['network_id'] = $networkUser['id'];
            if(isset($attributes['id'])) unset($attributes['id']);

            $exists = static::query()->where('network_id', $networkUser['id'])->first();
            if($exists) return $exists;

            return static::query()->create($attributes);
        }

        return $networkUser;
    }

}