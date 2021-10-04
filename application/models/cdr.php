<?php

class Cdr extends Eloquent
{
	public static $table = 'cdr';
	public static $key = 'uniqueid';

	public static function format_billsec($t)
	{
		if ($t >= 3600)
			return sprintf("%02d%s%02d%s%02d", floor($t/3600), ':', ($t/60)%60, ':', $t%60);
		else
			return sprintf("%02d%s%02d", ($t/60)%60, ':', $t%60);
	}

        public static function format_agent_billsec($t)
        {
		if (!$t) {
			return '';
		}

		return self::format_billsec($t);
        }

	public static function format_src_dst($cdr, $type)
	{
		$name = $type . '_name';
		if ($cdr->$name)
		{
			return $cdr->$name . ' (' . $cdr->$type . ')';
		}
		elseif ($type == 'dst' AND $cdr->description)
		{
			return $cdr->description . ' (' . $cdr->$type . ')';
		}
        elseif ($type == 'src' AND $cdr->$type == '153')
        {
            return $cdr->cnam . ' (' . $cdr->cnum . ')';
        }
		else
		{
			return $cdr->$type;
		}
	}

	public static function format_clid($clid)
	{
		preg_match('/"(.+)"/', $clid, $matches);
		return isset($matches[1]) ? $matches[1] : '';
	}

	public static function get_options($name)
	{
		if ($name == 'did')
		{
			$rows = DB::table('cdr')->select('did')->distinct()->get();
            foreach ($rows as $row) {
                $options[$row->did] = $row->did;
            }
        }

		if ($name == 'ringgroup')
		{
			$options = array('' => '');
			$ringgroups = DB::table('asterisk.ringgroups')->get();
			foreach ($ringgroups as $rg) {
				$options[$rg->grpnum] = $rg->grpnum . ' / ' . $rg->description;
			}
		}

        if ($name == 'status')
		{
			$options = array(
				'' => '',
				'ANSWERED'   => __('misc.answered'),
				'NO ANSWER'  => __('misc.no answer'),
				'FAILED'     => __('misc.failed'),
				'BUSY'       => __('misc.busy'),
			);
		}

        if ($name == 'scope')
		{
			$calldir = Input::get('calldir');
			$in = 'İçi';
			$out = 'Dışı';
			if ($calldir == 'in')
			{
				$in = 'İçinden';
				$out = 'Dışından';
			}
			if ($calldir == 'out')
			{
				$in = 'İçine';
				$out = 'Dışına';
			}
			$options = array(
				'' => '',
				'in' => 'Dahili Aramalar',
				'out' => 'Dış Aramalar',
			);
		}

        if ($name == 'tag' || $name == 'tag_update')
		{
            static $call_tags = null;
            if ($call_tags === null) {
                $tagQueueCalls = parse_ini_file('/var/www/html/fop2/admin/plugins/tagQueueCalls/tagQueueCalls.ini');
                $call_tags = explode('|', $tagQueueCalls['call_tags']);
            }
            $options = array('' => '');
            if ($name != 'tag_update') {
                $options['null'] = '-- BOŞ --';
            }
            foreach ($call_tags as $call_tag)
			{
				$options[$call_tag] = $call_tag;
			}
		}

        if ($name == 'agent') {
            $options = array('' => '');
			$users = DB::table('asterisk.users')->select(array('extension', 'name'))->order_by('extension')->get();
			foreach ($users as $user) {
				$options[$user->name] = $user->name;
			}
        }

        return $options;
	}

    public static $tag_suffix = '~info5';

    public static function format_tag($tag)
    {
        return str_replace(self::$tag_suffix, '', $tag);
    }

    public static $per_page_options = array(
        10 => 10,
        25 => 25,
        50 => 50,
        100 => 100,
    );

    public static function format_datetime_input($input)
    {
        $datetime_parts = explode(' - ', $input);
        $date_parts = explode('.', $datetime_parts[0]);
        $date_parts = array_reverse($date_parts);
        $datetime_parts[0] = implode('-', $date_parts);
        return implode(' ', $datetime_parts);
    }

    public static function export_url()
    {
        $url = $_SERVER['REQUEST_URI'];
        $url_parts = parse_url($url);
        if (isset($url_parts['query'])) {
            $url .= '&export';
        }
        else {
            $url .= '?export';
        }
        return $url;
    }

	public static function getTemporaryOggDir()
	{
		return path('storage') . 'tmp';
	}

}
