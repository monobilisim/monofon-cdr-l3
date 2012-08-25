<?php

class Cdr extends Eloquent
{
	public static $table = 'cdr';
	public static $key = 'uniqueid';
	
	public static function format_billsec($billsec)
	{
		return $billsec . 's' . ($billsec > 60 ? ' (' . ltrim(gmdate('i:s', $billsec), '0') . ')' : '');
	}
	
	public static function format_channel($cdr, $type)
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
		else
		{
			return $cdr->$type;
		}
	}
	
	public static function get_options($name)
	{
		if ($name == 'ringgroup')
		{
			$options = array(
				'' => ''
			);
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
		elseif ($name == 'calldir')
		{
			$options = array(
				'' => '',
				'in' => 'Gelen',
				'out' => 'Giden',
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
		return $options;
	}

}