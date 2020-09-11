<?php

class Remove_Button_From_Users {

        /**
         * Make changes to the database.
         *
         * @return void
         */
        public function up()
        {
                Schema::table('users', function($table)
                {
                        $table->drop_column('buttons');
                });
        }

        /**
         * Revert the changes to the database.
         *
         * @return void
         */
        public function down()
        {
                Schema::table('users', function($table)
                {
                        $table->boolean('buttons')->default(1);
                });
        }

}
