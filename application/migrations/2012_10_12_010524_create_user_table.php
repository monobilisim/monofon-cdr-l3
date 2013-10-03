<?php

class Create_User_Table {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('users', function($table)
		{
			$table->increments('id');
			$table->string('username', 50)->unique;
			$table->string('password', 60);
			$table->string('role', 10);
			$table->string('perm', 50);
			$table->timestamps();
		});

		DB::table('users')->insert(array(
			'username' => 'mono',
			'password' => '$2a$08$2TEPv3PL6x8q9lg0weE9EuYx4iuc5MhDm6nEupnm2KfPdO/LxMYMW',
			'role' => 'admin',
		));
		DB::table('users')->insert(array(
			'username' => 'admin',
			'password' => Hash::make('admin'),
			'role' => 'admin',
		));
	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::create('users', function($table)
		{
			$table->drop();
		});
	}

}
