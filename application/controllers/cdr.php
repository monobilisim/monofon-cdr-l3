<?php

class Cdr_Controller extends Base_Controller {
	
	public function __construct()
	{
		parent::__construct();
		$this->filter('before', 'auth');
	}

	public function before()
	{
		parent::before();
		Asset::add('jquery-ui', 'jquery-ui/jquery-ui-1.8.22.custom.min.js');
		Asset::add('jquery-ui', 'jquery-ui/flick/jquery-ui-1.8.22.custom.css');
		Asset::add('cdr', 'js/cdr.js');
		Asset::add('jquery-ui-tr', 'jquery-ui/jquery.ui.datepicker-tr.js');
	}
	
	public function action_index()
	{
		Config::set('database.default', 'asterisk');
		
		$per_page = Input::get('per_page', 10);
		$default_sort = array(
			'sort' => 'calldate',
			'dir' => 'desc',
		);
		$sort = Input::get('sort', $default_sort['sort']);
		$dir = Input::get('dir', $default_sort['dir']);
		
		$datestart = date('Y-m-d', strtotime(Input::get('datestart', date('Y-m-d'))));
		$dateend = date('Y-m-d', strtotime(Input::get('dateend', date('Y-m-d'))));
		$status = Input::get('status');
		$extension = Input::get('extension');
		$calldir = Input::get('calldir');
		$scope = Input::get('scope');
		
		$cdrs = DB::table('cdr')->select(array('*', 'users_src.name AS src_name', 'users_dst.name AS dst_name'))
			->left_join('asterisk.ringgroups', 'dst', '=', 'asterisk.ringgroups.grpnum')
			->left_join('asterisk.users AS users_src', 'src', '=', 'users_src.extension')
			->left_join('asterisk.users AS users_dst', 'dst', '=', 'users_dst.extension')
			->raw_where("DATE(calldate) BETWEEN '$datestart' AND '$dateend'");
		
		if ($status) $cdrs->where('disposition', '=', $status);		
		
		$extension_filters = array();
		if (Auth::user()->perm) $extension_filters['perm'] = Auth::user()->perm;
		if ($extension) $extension_filters['ext'] = $extension;
		
		$wheres = array();
		
		// Apply extension filter with calldir
		foreach ($extension_filters as $type => $extension_filter)
		{
			$wheres[] = self::build_extension_where_clauses($extension_filter, $calldir, $type, $extension);
		}
		
		// Apply scope filter
		if ($scope)
		{
			if ($calldir)
			{
				$field = ($calldir == 'in' ? 'src' : 'dst');
				if ($scope == 'in') $wheres[] = "CHAR_LENGTH($field) != 11";
				if ($scope == 'out') $wheres[] = "CHAR_LENGTH($field) = 11";	
			}
			else
			{
				if ($scope == 'in') $wheres[] = "(CHAR_LENGTH(src) != 11 AND CHAR_LENGTH(dst) != 11)";
				if ($scope == 'out') $wheres[] = "(CHAR_LENGTH(src) = 11 OR CHAR_LENGTH(dst) = 11)";
			}
		}
		
		// No extension or scope filter, only calldir
		if (empty($extension_filters) AND empty($scope) AND $calldir)
		{
			if ($calldir == 'in') $wheres[] = "CHAR_LENGTH(src) = 11";
			if ($calldir == 'out') $wheres[] = "CHAR_LENGTH(dst) = 11";
		}
		
		foreach ($wheres as $where)
		{
			$cdrs->raw_where($where);
		}
		$cdrs->order_by($sort, $dir);
		$cdrs = $cdrs->paginate($per_page);
		
		$cdrs = PaginatorSorter::make($cdrs->results, $cdrs->total, $per_page, $default_sort);
		
		$per_page_options = array(
			10 => 10,
			25 => 25,
			50 => 50,
			100 => 100
		);

		$this->layout->nest('content', 'cdr.index', array(
			'cdrs' => $cdrs,
			'per_page_options' => $per_page_options,
		));
	}
	
	protected static function build_extension_where_clauses($extension, $calldir, $type, $extension_input)
	{
		$filters = explode(';', $extension);
		$clauses = array();
		
		foreach ($filters as $filter)
		{
		
			preg_match('/X+/', $filter, $match);
			if ($match)
			{
				$regex = '^' . str_replace($match[0], '[0-9]{'.strlen($match[0]).'}', $filter) . '$';
				$clauses[] = "REGEXP '$regex'";
			}
			elseif (strpos($filter, '-'))
			{
				$numbers = explode('-', $filter);
				$clauses[] = "BETWEEN $numbers[0] AND $numbers[1]";
			}
			elseif (strpos($filter, ','))
			{
				$clauses[] = "IN ($filter)";
			}
			else
			{
				$clauses[] = "= '$filter'";
			}

		}
		
		// if there is extension filter by a restricted user ignore calldir and treat perm filter as an allowed realm
		if ($type == 'perm' AND $extension_input) $calldir = false;
		
		foreach ($clauses as $key => $clause)
		{
			if ($calldir)
			{
				$field = ($calldir == 'in' ? 'dst' : 'src');
				$clauses[$key] = $field . ' ' . $clause;
			}
			else
			{
				$clauses[$key] = 'src ' . $clause . ' OR ' . 'dst '. $clause;
			}
		}
		
		$where = '(' . implode(' OR ', $clauses) . ')';
		
		return $where;
	}
	
	public function action_listen($uniqueid)
	{
		$html = '<embed src="/wavplayer.swf?gui=full&autoplay=true&h=20&w=300&sound=/cdr/download/' . $uniqueid . '" width="300" height="20" scale="noscale" bgcolor="#dddddd"/>';
		return $html;
	}
	
	public function action_download($uniqueid)
	{
		Config::set('database.default', 'asterisk');
		$cdr = Cdr::find($uniqueid);
		$file = self::retrieve_file($cdr);
		
		$headers = array(
			'content-type' => '',
			'content-disposition' => 'attachment; filename=' . $file['name'],
			'X-Accel-Redirect' => '/monitor/' . $file['path'] . '/' . $file['name'],
		);
		return Response::make(null, 200, $headers);
	}
	
	public static function retrieve_file($cdr)
	{
		$file = array();
		if (Config::get('ordered_monitor') === true)
		{
			$file['path'] = date('Y/m/d', strtotime($cdr->calldate));
		}
		else
		{
			$file['path'] = "";
		}
		$file['name'] = str_replace('audio:', '', $cdr->userfield);
		return $file;
	}
	
}