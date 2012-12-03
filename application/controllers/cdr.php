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
		Asset::add('jquery-ui', 'jquery-ui/jquery-ui-1.9.1.custom.min.js');
		Asset::add('jquery-ui-tr', 'jquery-ui/jquery.ui.datetimepicker-tr.js');
		Asset::add('jquery-ui-timepicker', 'jquery-ui/jquery-ui-timepicker-addon.js');
		Asset::add('cdr', 'js/cdr.js');

		Asset::add('jquery-ui', 'jquery-ui/smoothness/jquery-ui-1.9.1.custom.min.css');
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

		extract(Input::get());

		$datestart = !empty($datestart) ? self::format_datetime_input($datestart) : date('Y-m-d 00:00');
		$dateend = !empty($dateend) ? self::format_datetime_input($dateend) : date('Y-m-d 23:59');
		
		$cdrs = DB::table('cdr')->select(array('*', 'users_src.name AS src_name', 'users_dst.name AS dst_name'))
			->left_join('asterisk.ringgroups', 'dst', '=', 'asterisk.ringgroups.grpnum')
			->left_join('asterisk.users AS users_src', 'src', '=', 'users_src.extension')
			->left_join('asterisk.users AS users_dst', 'dst', '=', 'users_dst.extension')
			->raw_where("calldate BETWEEN '$datestart' AND '$dateend'");
		
		if (!empty($status)) $cdrs->where('disposition', '=', $status);		
		
		$number_filters = array();
		if (Auth::user()->perm) $number_filters['perm'] = Auth::user()->perm;
		if (!empty($src)) $number_filters['src'] = $src;
		if (!empty($dst)) $number_filters['dst'] = $dst;
		if (!empty($src_dst)) $number_filters['src_dst'] = $src_dst;
		
		$wheres = array();
		
		// Apply number filter
		foreach ($number_filters as $type => $number_filter)
		{
			$wheres[] = self::build_number_where_clauses($type, $number_filter);
		}
		
		// Apply scope filter
		if (!empty($scope))
		{
			if ($scope == 'in') $wheres[] = "(CHAR_LENGTH(src) < 7 AND CHAR_LENGTH(dst) < 7)";
			if ($scope == 'out') $wheres[] = "(CHAR_LENGTH(src) >= 7 OR CHAR_LENGTH(dst) >= 7)";
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
	
	protected static function build_number_where_clauses($type, $number_filter)
	{
		$filters = explode(';', $number_filter);
		$clauses = array();
		
		foreach ($filters as $filter)
		{
		
			preg_match('/X+/', $filter, $match);
			if ($match)
			{
				$regex = str_replace($match[0], '[0-9]{'.strlen($match[0]).'}', $filter);
				if (strlen($filter) >= 7) $regex = '[0-9]*' . $regex;
				$regex = '^' . $regex . '$';
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
				$clauses[] = strlen($filter) >= 7 ? "LIKE '%$filter'" : "= '$filter'";
			}

		}

		foreach ($clauses as $key => $clause)
		{
			if ($type == 'perm' OR $type == 'src_dst')
			{
				$clauses[$key] = 'src ' . $clause . ' OR ' . 'dst '. $clause;
			}
			if ($type == 'src')
			{
				$clauses[$key] = 'src ' . $clause;
			}
			if ($type == 'dst')
			{
				$clauses[$key] = 'dst ' . $clause;
			}
		}
		
		$where = '(' . implode(' OR ', $clauses) . ')';
		
		return $where;
	}

	protected static function format_datetime_input($input)
	{
		$datetime_parts = explode(' - ', $input);
		$date_parts = explode('.', $datetime_parts[0]);
		$date_parts = array_reverse($date_parts);
		$datetime_parts[0] = implode('-', $date_parts);
		return implode(' ', $datetime_parts);
	}
	
	public function action_listen($uniqueid, $calldate)
	{
		$html = '<embed src="/wavplayer.swf?gui=full&autoplay=true&h=20&w=300&sound=/cdr/download/' . $uniqueid . '/' . $calldate .'" width="300" height="20" scale="noscale" bgcolor="#dddddd"/>';
		return $html;
	}
	
	public function action_download($uniqueid, $calldate)
	{
		Config::set('database.default', 'asterisk');
		$cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $calldate))->first();
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
		if (Config::get('application.ordered_monitor') === true)
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

	public static function cdr_file_exists($cdr)
	{
		$file = self::retrieve_file($cdr);
		return file_exists('file:///var/spool/asterisk/monitor/' . $file['path'] . '/' . $file['name'];
	}
	
}