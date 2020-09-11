<?php

class Add_Button_Download_And_Button_Listen_To_Users {

        /**
         * Make changes to the database.
         *
         * @return void
         */
        public function up()
        {
                Schema::table('users', function($table)
                {
                        $table->boolean('buttons_download')->default(1);
                        $table->boolean('buttons_listen')->default(1);
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
                        $table->drop_column('buttons_download');
                        $table->drop_column('buttons_listen');
                });
        }

}
