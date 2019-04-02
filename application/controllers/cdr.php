<?php

class Cdr_Controller extends Base_Controller
{

    public static function cdr_file_exists($cdr)
    {
        $file = self::retrieve_file($cdr);
        //return file_exists('file:///var/spool/asterisk/monitor/' . $file['path'] . '/' . $file['name']);
        return true;
    }

    public static function retrieve_file($cdr)
    {
        $filefield = Config::get('application.filefield');

        $file = array();
        if (Config::get('application.date_sorted_monitor') === true) {
            $file['path'] = date('Y/m/d', strtotime($cdr->calldate));
        } else {
            $file['path'] = "";
        }
        $file['name'] = basename(preg_replace('/^audio:/', '', $cdr->$filefield));
        $ext = Config::get('application.extension');
        if (strpos($file['name'], ".$ext") === false && strpos($file['name'], '.ogg') === false) {
            $file['name'] .= ".$ext";
        }
        return $file;
    }

    public function before()
    {
        parent::before();
        
        // js
        Asset::add('jquery-ui', 'jquery-ui/jquery-ui-1.9.1.custom.min.js');
        Asset::add('jquery-ui-tr', 'jquery-ui/jquery.ui.datetimepicker-tr.js');
        Asset::add('jquery-ui-timepicker', 'jquery-ui/jquery-ui-timepicker-addon.js');
        Asset::add('cdr', 'js/cdr.js');
        Asset::add('wavesurfer', 'js/wavesurfer.min.js');
        Asset::add('wavesurfer-cursor', 'js/wavesurfer.cursor.js');
        Asset::add('wavesurfer-timeline', 'js/wavesurfer.timeline.js');

        // css
        Asset::add('jquery-ui', 'jquery-ui/smoothness/jquery-ui-1.9.1.custom.min.css');
        
        Config::set('database.default', 'asterisk');
    }
    
    private static function getCdrQuery()
    {
        $query = DB::table('cdr')->select(array(
            'cdr.*',
            'ringgroups.description',
            'users_src.name AS src_name',
            'users_dst.name AS dst_name'
        ))
            ->left_join('asterisk.ringgroups', 'dst', '=', 'asterisk.ringgroups.grpnum')
            ->left_join('asterisk.users AS users_src', 'src', '=', 'users_src.extension')
            ->left_join('asterisk.users AS users_dst', 'dst', '=', 'users_dst.extension');
        if (Config::get('application.call_tags')) {
            $query->selects[] = 'queue_log.data1 as tag';
            $query->left_join('asteriskrealtime.queue_log', function($join) {
                $join->on('queue_log.callid', '=', 'cdr.linkedid');
                $join->on('queue_log.event', '=', DB::raw("'UPDATEFIELD'"));
            });
        }
        return $query;
    }

    public function action_index()
    {
        $filefield = Config::get('application.filefield');

        $per_page = Input::get('per_page', 10);
        $default_sort = array(
            'sort' => 'calldate',
            'dir' => 'desc',
        );
        $sort = Input::get('sort', $default_sort['sort']);
        $dir = Input::get('dir', $default_sort['dir']);

        extract(Input::get());

        $datestart = !empty($datestart) ? Cdr::format_datetime_input($datestart) : date('Y-m-d 00:00');
        $dateend = !empty($dateend) ? Cdr::format_datetime_input($dateend) : date('Y-m-d 23:59');

        $query = self::getCdrQuery();
        $query->raw_where("calldate BETWEEN '$datestart' AND '$dateend'");

        if (!Auth::user()->allrows) {
            $query->where($filefield, '!=', '');
        }

        if (!empty($status)) {
            $query->where('disposition', '=', $status);
        }
        if (!empty($dstchannel)) {
            $query->where('dstchannel', 'LIKE', "%$dstchannel%");
        }
        if (!empty($accountcode)) {
            $query->where('accountcode', 'LIKE', "%$accountcode%");
        }
        if (!empty($did)) {
            $query->where('did', '=', $did);
        }
        if (!empty($tag)) {
            if ($tag == 'null') {
                $query->where_null('queue_log.data1');
            }
            else {
                $query->where('queue_log.data1', '=', $tag.Cdr::$tag_suffix);
            }
        }

        $number_filters = array();
        if (Auth::user()->perm) {
            $number_filters['perm'] = Auth::user()->perm;
        }
        if (!empty($src)) {
            $number_filters['src'] = $src;
        }
        if (!empty($dst)) {
            $number_filters['dst'] = $dst;
        }
        if (!empty($src_dst)) {
            $number_filters['src_dst'] = $src_dst;
        }

        $wheres = array();

        // Apply number filter
        foreach ($number_filters as $type => $number_filter) {
            $wheres[] = self::build_number_where_clauses($type, $number_filter);
        }

        // Apply scope filter
        if (!empty($scope)) {
            if ($scope == 'in') {
                $wheres[] = "(CHAR_LENGTH(src) < 7 AND CHAR_LENGTH(dst) < 7)";
            }
            if ($scope == 'out') {
                $wheres[] = "(CHAR_LENGTH(src) >= 7 OR CHAR_LENGTH(dst) >= 7)";
            }
        }

        foreach ($wheres as $where) {
            $query->raw_where($where);
        }

        $total_billsec = $query->sum('billsec');

        $query->order_by($sort, $dir);
        $query = $query->paginate($per_page);

        $cdrs = PaginatorSorter::make($query->results, $query->total, $per_page, $default_sort);

        $display_agent_billsec = false;
        if (Config::get('application.agent_billsec')) {
            $display_agent_billsec = true;
            foreach ($query->results as $result) {
                $result->agent_billsec = self::calculate_agent_billsec($result->uniqueid);
            }
        }

        $this->layout->nest('content', 'cdr.index', array(
            'cdrs' => $cdrs,
            'filefield' => $filefield,
            'per_page_options' => Cdr::$per_page_options,
            'total_billsec' => $total_billsec,
            'buttons' => Auth::user()->buttons,
            'display_agent_billsec' => $display_agent_billsec,
        ));
    }
    
    public function action_update()
    {
        $linkedid = Input::get('linkedid');
        $tag = Input::get('tag');
        
        $events = array(
            'ENTERQUEUE',
            'UPDATEFIELD',
        );
        
        $qevents = array();
        
        foreach ($events as $event) {
            $event_id = DB::table('qstats.qevent')->where('event', '=', $event)->only('event_id');
            $qevents[$event] = (int) $event_id;
        }
        
        $tag_with_suffix = $tag . Cdr::$tag_suffix;
        
        DB::table('asteriskrealtime.queue_log')
                ->where('callid', '=', $linkedid)
                ->where('event', '=', 'UPDATEFIELD')
                ->update(array('data1' => $tag_with_suffix));
                
        DB::table('qstats.queue_stats')
            ->where('uniqueid', '=', $linkedid)
            ->where('qevent', '=', $qevents['ENTERQUEUE'])
            ->update(array('info5' => $tag));
        
        DB::table('qstats.queue_stats')
            ->where('uniqueid', '=', $linkedid)
            ->where('qevent', '=', $qevents['UPDATEFIELD'])
            ->update(array('info1' => $tag_with_suffix));
        
        DB::table('qstats.queue_stats_mv')
            ->where('uniqueid', '=', $linkedid)
            ->update(array('info5' => $tag));
        
        return Redirect::back()
            ->with('message', 'Etiket güncellendi.')
            ->with('message_status', 'success');
    }

    protected static function build_number_where_clauses($type, $number_filter)
    {
        $filters = explode(';', $number_filter);
        $clauses = array();

        foreach ($filters as $filter) {

            preg_match('/X+/', $filter, $match);
            if ($match) {
                $regex = str_replace($match[0], '[0-9]{' . strlen($match[0]) . '}', $filter);
                if (strlen($filter) >= 7) {
                    $regex = '[0-9]*' . $regex;
                }
                $regex = '^' . $regex . '$';
                $clauses[] = "REGEXP '$regex'";
            } elseif (strpos($filter, '-')) {
                $numbers = explode('-', $filter);
                $clauses[] = "BETWEEN $numbers[0] AND $numbers[1]";
            } elseif (strpos($filter, ',')) {
                $clauses[] = "IN ($filter)";
            } else {
                $clauses[] = strlen($filter) >= 7 ? "LIKE '%$filter%'" : "= '$filter'";
            }

        }

        foreach ($clauses as $key => $clause) {
            if ($type == 'perm' OR $type == 'src_dst') {
                $clauses[$key] = 'src ' . $clause . ' OR ' . 'dst ' . $clause;
            }
            if ($type == 'src') {
                $clauses[$key] = 'src ' . $clause;
            }
            if ($type == 'dst') {
                $clauses[$key] = 'dst ' . $clause;
            }
        }

        $where = '(' . implode(' OR ', $clauses) . ')';

        return $where;
    }

    public function action_view($uniqueid, $timestamp)
    {
        $filefield = Config::get('application.filefield');

        $per_page = Input::get('per_page', 10);
        $default_sort = array(
            'sort' => 'calldate',
            'dir' => 'desc',
        );
        $sort = Input::get('sort', $default_sort['sort']);
        $dir = Input::get('dir', $default_sort['dir']);

        $cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $timestamp))->first();

        $related_uniqueids = self::get_related_uniqueids($cdr->uniqueid, $cdr->linkedid);
        if (!$related_uniqueids) $related_uniqueids = array('yok');

        $related_cdrs = self::get_cdrs_by_uniqueids($related_uniqueids);
        $related_cels = self::get_cels_by_uniqueids($related_uniqueids)->get();
        $related_queue_logs = self::get_queue_logs_by_uniqueids($related_uniqueids)->get();

        $total_billsec = $related_cdrs->sum('billsec');

        $related_cdrs->order_by($sort, $dir);
        $related_cdrs = $related_cdrs->paginate($per_page);

        $related_cdrs = PaginatorSorter::make($related_cdrs->results, $related_cdrs->total, $per_page, $default_sort);

        $this->layout->nest('content', 'cdr.view', array(
            'cdr' => $cdr,
            'cdrs' => $related_cdrs,
            'cels' => $related_cels,
            'queue_logs' => $related_queue_logs,
            'filefield' => $filefield,
            'per_page_options' => Cdr::$per_page_options,
            'total_billsec' => $total_billsec,
            'buttons' => $buttons,
            'display_agent_billsec' => false,
        ));
    }

    private static function calculate_agent_billsec($uniqueid)
    {
        $related_uniqueids = self::get_related_uniqueids($uniqueid);
        $related_cels = self::get_cels_by_uniqueids($related_uniqueids)->get();

        $call_transferred = false;
        $agent_exten = null;
        $last_event = null;
        $billsec = 0;

        foreach ($related_cels as $cel) {
            // Eğer bir BLINDTRANSFER veya ATTENDEDTRANSFER event'i varsa
            // çağrı transfer edilmiştir
            if (in_array($cel->eventtype, array('BLINDTRANSFER', 'ATTENDEDTRANSFER'))) {
                $call_transferred = true;
            }

            // Temsilcinin dahilisi henüz tespit edilmemişken gelen
            // ilk from-internal context'indeki ANSWER event'inin
            // exten'i temsilcinin dahilisidir.
            if ($agent_exten === null && $cel->eventtype === 'ANSWER' && $cel->context === 'from-internal') {
                $agent_exten = $cel->exten;
            }

            // Temsilcinin dahilisi tespit edilmişse, gelen event'leri
            // kullanarak hesaplamaları yap
            if ($agent_exten !== null) {
                if (
                    ($cel->eventtype === 'ANSWER' && $cel->context === 'from-internal' && $cel->exten === $agent_exten) ||
                    $cel->eventtype === 'HOLD_END'
                ) {
                    $last_event = array(
                        'type' => 'start',
                        'time' => strtotime($cel->eventtime),
                    );
                } elseif (
                    $cel->eventtype === 'HOLD_START' ||
                    ($cel->context === 'from-internal' && in_array($cel->eventtype, array('BLINDTRANSFER', 'HANGUP')))
                ) {
                    if ($last_event['type'] === 'start') {
                        $billsec += strtotime($cel->eventtime) - $last_event['time'];
                    }
                    $last_event = array(
                        'type' => 'end',
                        'time' => strtotime($cel->eventtime),
                    );
                }
            }

            // Çağrının temsilcideki kısmını tamamen sonlandıran event'leri gördüğünde
            // hesaplamayı bırak
            if ($agent_exten !== null && $cel->context === 'from-internal' && in_array($cel->eventtype, array('BLINDTRANSFER', 'HANGUP'))) {
                break;
            }
        }

        if ($agent_exten !== null) {
            return $billsec;
        }

        return null;
    }

    private static function get_related_uniqueids($uniqueid, $linkedid = 'dev')
    {
        $pass = DB::table('cel')
            ->select(array('uniqueid', 'linkedid'))
            ->where('uniqueid', '=', $uniqueid)
            ->or_where('linkedid', '=', $linkedid)
            ->get();

        $last_criteria = array();
        $next = array();
        $done = false;

        while (!$done) {
            unset($next);
            $next = array();
            foreach ($pass as $set) {
                $next[] = $set->uniqueid;
                $next[] = $set->linkedid;
            }
            $next = array_unique($next);
            sort($next);

            if ($next === $last_criteria) {
                $done = true;
                continue;
            }
            unset($pass);

            $pass = DB::table('cel')
                ->select(array('uniqueid', 'linkedid'))
                ->where_in('uniqueid', $next)
                ->or_where_in('linkedid', $next)
                ->get();

            $last_criteria = $next;
            $next = array();
        }

        $ids = array();

        foreach ($pass as $cel) {
            $ids[] = $cel->uniqueid;
            $ids[] = $cel->linkedid;

            $ids = array_unique($ids);
            sort($ids);
        }

        return $ids;
    }

    private static function get_cdrs_by_uniqueids($uniqueids)
    {
        $query = self::getCdrQuery();
        $query->where_in('uniqueid', $uniqueids);
        return $query;
    }

    private static function get_cels_by_uniqueids($uniqueids)
    {
        return DB::table('cel')->select('*')
            ->where_in('uniqueid', $uniqueids)
            ->or_where_in('linkedid', $uniqueids)
            ->or_where_in('accountcode', $uniqueids);
    }

    private static function get_queue_logs_by_uniqueids($uniqueids)
    {
        Config::set('database.default', 'asteriskrealtime');

        return DB::table('queue_log')->select('*')
            ->where_in('callid', $uniqueids);
    }

    public function action_listen($uniqueid, $calldate)
    {
        $cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $calldate))->first();
        $file = self::retrieve_file($cdr);
        $file_info = pathinfo($file['name']);

        $html = '';
        if ($file_info['extension'] === 'WAV') {
            $html .= '<embed src="/wavplayer.swf?gui=full&autoplay=true&h=20&w=300&sound=/cdr/download/' . $uniqueid . '/' . $calldate . '" width="300" height="20" scale="noscale" bgcolor="#dddddd"/>';
        } elseif ($file_info['extension'] === 'ogg') {
            $html = <<<HTML
                <div id="waveform-progress-wrapper">
                    <div id="waveform-progress" class="progress progress-striped active"><div class="bar" style="width: 0%;"></div></div>
                </div>
                <div id="waveform"></div>
                <div id="wave-timeline"></div>
                
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
                        height: 120,
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
                            }),
                            WaveSurfer.timeline.create({
                                container: "#wave-timeline"
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
        $cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $calldate))->first();
        $file = self::retrieve_file($cdr);

        if (file_exists('file:///var/spool/asterisk/monitor/' . $file['path'] . '/' . $file['name'])) {
            return Response::download('/var/spool/asterisk/monitor/' . $file['path'] . '/' . $file['name'],
                $file['name']);
        }

        if ($remote_base_url = Config::get('application.remote_base_url')) {
            $file_url = $remote_base_url . '/monitor/' . $file['path'] . '/' . urlencode($file['name']);
            header('Content-Transfer-Encoding: Binary');
            header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
            readfile($file_url);
        }
    }
}
