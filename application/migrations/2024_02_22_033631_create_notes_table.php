<?php

class Create_Notes_Table
{
    /**
     * Make changes to the database.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notes', function ($table) {
            $table->increments('id');
            $table->string('uniqueid')->unique();
            $table->text('note');
            $table->integer('created_by');
            $table->integer('updated_by')->nullable();
            $table->timestamps();
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
