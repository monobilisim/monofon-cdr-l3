<?php

class User extends Eloquent
{
    public static function rules()
    {
        $rules = array(
            'username' => 'required|min:3|max:50|unique:users',
            'password' => 'required',
        );

        return $rules;
    }
}
