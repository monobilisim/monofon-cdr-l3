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
        Asset::add('wavesurfer', 'js/wavesurfer.min.js');
        Asset::add('wavesurfer-cursor', 'js/wavesurfer.cursor.js');

		Asset::add('jquery-ui', 'jquery-ui/smoothness/jquery-ui-1.9.1.custom.min.css');
	}
	
	public function action_index()
	{
		Config::set('database.default', 'asterisk');

		$filefield = Config::get('application.filefield');
		
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
		
		if (Config::get('application.multiserver'))
		{
			$cdrs = DB::table('cdr')->select('*')
				->raw_where("calldate BETWEEN '$datestart' AND '$dateend'");
		}
		else
		{
			$cdrs = DB::table('cdr')->select(array(
					'*',
					'users_src.name AS src_name',
					'users_dst.name AS dst_name'
				))
				->left_join('asterisk.ringgroups', 'dst', '=', 'asterisk.ringgroups.grpnum')
				->left_join('asterisk.users AS users_src', 'src', '=', 'users_src.extension')
				->left_join('asterisk.users AS users_dst', 'dst', '=', 'users_dst.extension')
				->raw_where("calldate BETWEEN '$datestart' AND '$dateend'");
		}

		if (!Auth::user()->allrows) $cdrs->where($filefield, '!=', '');
		
		if (!empty($status)) $cdrs->where('disposition', '=', $status);		
		if (!empty($server)) $cdrs->where('server', '=', $server);
		if (!empty($dstchannel)) $cdrs->where('dstchannel', 'LIKE', "%$dstchannel%");
		if (!empty($accountcode)) $cdrs->where('accountcode', 'LIKE', "%$accountcode%");
		
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

		$total_billsec = $cdrs->sum('billsec');

		$cdrs->order_by($sort, $dir);
		$cdrs = $cdrs->paginate($per_page);
		
		$cdrs = PaginatorSorter::make($cdrs->results, $cdrs->total, $per_page, $default_sort);
		
		$per_page_options = array(
			10 => 10,
			25 => 25,
			50 => 50,
			100 => 100
		);

		$colspan = 6;
		if (Config::get('application.multiserver')) $colspan++;
		if (Config::get('application.dstchannel')) $colspan++;
		if (Config::get('application.clid')) $colspan++;
		if (Config::get('application.accountcode')) $colspan++;

		$buttons = Auth::user()->buttons;
		if (!$buttons) $colspan--;

		$this->layout->nest('content', 'cdr.index', array(
			'cdrs' => $cdrs,
			'filefield' => $filefield,
			'per_page_options' => $per_page_options,
			'total_billsec' => $total_billsec,
			'colspan' => $colspan,
			'buttons' => $buttons,
		));
	}
	
	public function action_view($uniqueid, $timestamp)
	{
		Config::set('database.default', 'asterisk');

		$cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $timestamp))->first();

		$this->layout->nest('content', 'cdr.view', array(
			'cdr' => $cdr
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
				$clauses[] = strlen($filter) >= 7 ? "LIKE '%$filter%'" : "= '$filter'";
			}

		}

		foreach ($clauses as $key => $clause)
		{
			if ($type == 'perm' OR $type == 'src_dst')
			{
				$clauses[$key] = 'src ' . $clause . ' OR ' . 'dst ' . $clause . ' OR ' . 'cnum ' . $clause;
			}
			if ($type == 'src')
			{
				$clauses[$key] = 'src ' . $clause . ' OR ' . 'cnum ' . $clause;
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
        Config::set('database.default', 'asterisk');
        $cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $calldate))->first();
        $file = self::retrieve_file($cdr);
        $file_info = pathinfo($file['name']);

        $html = '';
        if ($file_info['extension'] === 'WAV') {
            $html .= '<embed src="/wavplayer.swf?gui=full&autoplay=true&h=20&w=300&sound=/cdr/download/' . $uniqueid . '/' . $calldate . '" width="300" height="20" scale="noscale" bgcolor="#dddddd"/>';
        } elseif ($file_info['extension'] === 'ogg') {
            $html = <<<HTML
                <div id="waveform-progress-wrapper">
                    <div id="waveform-progress" class="progress progress-striped active"><div class="bar" style="width: 0;"></div></div>
                </div>
                <div id="waveform"></div>
                
                <div class="controls">
                    <button data-toggle="tooltip" data-placement="bottom" title="2 saniye geri" class="btn" data-action="backward"><i class="icon-backward"></i></button>
                    <button data-toggle="tooltip" data-placement="bottom" title="Oynat/Duraklat" class="btn" data-action="play"><i class="icon-play"></i> / <i class="icon-pause"></i> (<span id="time-current" style="font-family:monospace;">00:00.000</span> / <span id="time-total" style="font-family:monospace;">00:00.000</span>)</button>
                    <button data-toggle="tooltip" data-placement="bottom" title="2 saniye ileri" class="btn" data-action="forward"><i class="icon-forward"></i></button>
                </div>
                  
                <script>
                    $('[data-toggle="tooltip"]').tooltip();
                
                    var wavesurfer = WaveSurfer.create({
                        container: '#waveform',
                        waveColor: '#828282',
                        progressColor: '#0088CC',
                        height: 80,
                        barHeight: 1,
                        skipLength: 2,
                        plugins: [
                            WaveSurfer.cursor.create({
                                showTime: true,
                                opacity: 0.7,
                                customShowTimeStyle: {
                                    'background-color': '#000',
                                    color: '#fff',
                                    padding: '2px',
                                    'font-size': '10px'
                                }
                            })
                        ]
                    });
                    
                    $('#waveform-progress-wrapper').show();
                    $('#waveform').css({'height': 0, 'overflow': 'hidden'});
        
                    wavesurfer.load('/cdr/download/$uniqueid/$calldate');
                    
                    wavesurfer.on('loading', function (percentage) {
                        $('#waveform-progress .bar').css('width', percentage.toString() + '%');
                    });
        
                    wavesurfer.on('ready', function () {
                        $('#waveform-progress-wrapper').hide();
                        $('#waveform').css({'height': '', 'overflow': ''});
                        wavesurfer.play();
                    });
        
                    wavesurfer.on('audioprocess', function() {
                        if(wavesurfer.isPlaying()) {
                            var totalTime = wavesurfer.getDuration(),
                            currentTime = wavesurfer.getCurrentTime();
        
                            document.getElementById('time-total').innerText = formatDuration(totalTime.toFixed(3));
                            document.getElementById('time-current').innerText = formatDuration(currentTime.toFixed(3));
                        }
                    });
        
                    $('.controls .btn').on('click', function(){
                        var action = $(this).data('action');
                        switch (action) {
                            case 'play':
                                wavesurfer.playPause();
                                break;
                            case 'backward':
                                wavesurfer.skipBackward();
                                break;
                            case 'forward':
                                wavesurfer.skipForward();
                                break;
                        }
                    });
                    
                    $("#listen").on('hidden', function() {
                      wavesurfer.destroy();  
                    })
                    
                    function formatDuration(duration) {
                        var totalSeconds = parseInt(duration.split('.')[0]);
                        var minutes = Math.floor(totalSeconds / 60);
                        var seconds = totalSeconds - minutes * 60;
                        var miliSeconds = duration.split('.')[1];
                      
                        minutes = minutes.toString();
                        seconds = seconds.toString();
                        
                        if (minutes.length === 1) {
                            minutes = '0' + minutes;
                        }
                        if (seconds.length === 1) {
                            seconds = '0' + seconds;
                        }
                    
                        return minutes + ':' + seconds + '.' + miliSeconds;
                    }
                </script>
HTML;

        }

        return $html;
	}
	
	public function action_download($uniqueid, $calldate)
	{
		Config::set('database.default', 'asterisk');
		$cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $calldate))->first();
		$file = self::retrieve_file($cdr);

		return Response::download('/var/spool/asterisk/monitor/' . $file['path'] . '/' . $file['name'], $file['name']);
	}
	
	public static function retrieve_file($cdr)
	{
		$filefield = Config::get('application.filefield');

		$file = array();
		if (Config::get('application.date_sorted_monitor') === true)
		{
			$file['path'] = date('Y/m/d', strtotime($cdr->calldate));
		}
		else
		{
			$file['path'] = "";
		}
		$file['name'] = basename(preg_replace('/^audio:/', '', $cdr->$filefield));
		$ext = Config::get('application.extension');
        if (strpos($file['name'], ".$ext") === false && strpos($file['name'], ".ogg") === false) {
            $file['name'] .= ".$ext";
        }
		return $file;
	}

	public static function cdr_file_exists($cdr)
	{
		$file = self::retrieve_file($cdr);
		return file_exists('file:///var/spool/asterisk/monitor/' . $file['path'] . '/' . $file['name']);
	}
	
}
