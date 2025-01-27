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
			$table->string('perm', 50)->nullable();
			$table->timestamps();
		});

		$user = new User;
		$user->username = 'mono';
		$user->password = Hash::make('mono');
		$user->role = 'admin';
		$user->save();

		$user = new User;
		$user->username = 'admin';
		$user->password = Hash::make('admin');
		$user->role = 'admin';
		$user->save();
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
