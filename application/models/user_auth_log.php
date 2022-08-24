<?php

class User_auth_log extends Eloquent
{
    public static $table = 'user_auth_log';
    public static $key = 'id';


    public function user()
    {
        return $this->has_many('User');
    }
}
