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

        public static function format_billsec_before_transfer($t)
        {
		if (is_null($t)) {
			return '';
		}

		return self::format_billsec($t);
        }
	
	public static function format_src_dst($cdr, $type)
	{
		if (Config::get('application.multiserver')) return $cdr->$type;

		$name = $type . '_name';
		if ($cdr->$name)
		{
			return $cdr->$name . ' (' . $cdr->$type . ')';
		}
		elseif ($type == 'dst' AND $cdr->description)
		{
			return $cdr->description . ' (' . $cdr->$type . ')';
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
		if ($name == 'ringgroup')
		{
			$options = array('' => '');
			$ringgroups = DB::table('asterisk.ringgroups')->get();
			foreach ($ringgroups as $rg) {
				$options[$rg->grpnum] = $rg->grpnum . ' / ' . $rg->description;
			}
		}
		elseif ($name == 'status')
		{
			$options = array(
				'' => '',
				'ANSWERED'   => __('misc.answered'),
				'NO ANSWER'  => __('misc.no answer'),
				'FAILED'     => __('misc.failed'),
				'BUSY'       => __('misc.busy'),
			);
		}
		elseif ($name == 'scope')
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
		elseif ($name == 'server')
		{
			$options = array('' => '');
			for ($i = 1; $i <= Config::get('application.multiserver'); $i++)
			{
				$options["cc$i"] = "cc$i";
			}
		}
		return $options;
	}

}
