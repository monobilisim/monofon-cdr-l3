<?php

class User_Auth_Log
{
    /**
     * Make changes to the database.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_auth_log', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->date('timestamp');
            $table->string('auth_type', 3);
        });
    }

    /**
     * Revert the changes to the database.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
