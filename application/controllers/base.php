<?php

class Base_Controller extends Controller {

	/**
	 * Catch-all method for requests that can't be matched.
	 *
	 * @param  string    $method
	 * @param  array     $parameters
	 * @return Response
	 */
	
	public $layout = 'layouts.master';
	
	public function __call($method, $parameters)
	{
		return Response::error('404');
	}

	public function before()
	{
		Asset::add('bootstrap_css', 'css/bootstrap.css');
		Asset::add('style', 'css/style.css');
		Asset::add('jquery', 'js/jquery-1.7.2.min.js');
		Asset::add('bootstrap_js', 'js/bootstrap.min.js');
	}

}