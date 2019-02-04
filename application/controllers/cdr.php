<?php

class Cdr_Controller extends Base_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->filter('before', 'auth');
    }

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
        Asset::add('jquery-ui', 'jquery-ui/jquery-ui-1.9.1.custom.min.js');
        Asset::add('jquery-ui-tr', 'jquery-ui/jquery.ui.datetimepicker-tr.js');
        Asset::add('jquery-ui-timepicker', 'jquery-ui/jquery-ui-timepicker-addon.js');
        Asset::add('cdr', 'js/cdr.js');

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

        if (Config::get('application.multiserver')) {
            $cdrs = DB::table('cdr')->select('*')
                ->raw_where("calldate BETWEEN '$datestart' AND '$dateend'");
        } else {
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

        if (!Auth::user()->allrows) {
            $cdrs->where($filefield, '!=', '');
        }

        if (!empty($status)) {
            $cdrs->where('disposition', '=', $status);
        }
        if (!empty($server)) {
            $cdrs->where('server', '=', $server);
        }
        if (!empty($dstchannel)) {
            $cdrs->where('dstchannel', 'LIKE', "%$dstchannel%");
        }
        if (!empty($accountcode)) {
            $cdrs->where('accountcode', 'LIKE', "%$accountcode%");
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
        if (Config::get('application.multiserver')) {
            $colspan++;
        }
        if (Config::get('application.dstchannel')) {
            $colspan++;
        }
        if (Config::get('application.clid')) {
            $colspan++;
        }
        if (Config::get('application.accountcode')) {
            $colspan++;
        }

        $buttons = Auth::user()->buttons;
        if (!$buttons) {
            $colspan--;
        }

        $this->layout->nest('content', 'cdr.index', array(
            'cdrs' => $cdrs,
            'filefield' => $filefield,
            'per_page_options' => $per_page_options,
            'total_billsec' => $total_billsec,
            'colspan' => $colspan,
            'buttons' => $buttons,
        ));
    }

    protected static function format_datetime_input($input)
    {
        $datetime_parts = explode(' - ', $input);
        $date_parts = explode('.', $datetime_parts[0]);
        $date_parts = array_reverse($date_parts);
        $datetime_parts[0] = implode('-', $date_parts);
        return implode(' ', $datetime_parts);
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
        Config::set('database.default', 'asterisk');

        $filefield = Config::get('application.filefield');

        $per_page = Input::get('per_page', 10);
        $default_sort = array(
            'sort' => 'calldate',
            'dir' => 'desc',
        );
        $sort = Input::get('sort', $default_sort['sort']);
        $dir = Input::get('dir', $default_sort['dir']);

        $cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $timestamp))->first();

        $related_uniqueids = self::get_related_uniueids($uniqueid);
        $related_cdrs = self::get_cdrs_by_uniqueids($related_uniqueids);


        $total_billsec = $related_cdrs->sum('billsec');

        $related_cdrs->order_by($sort, $dir);
        $related_cdrs = $related_cdrs->paginate($per_page);

        $related_cdrs = PaginatorSorter::make($related_cdrs->results, $related_cdrs->total, $per_page, $default_sort);

        $per_page_options = array(
            10 => 10,
            25 => 25,
            50 => 50,
            100 => 100
        );

        $colspan = 6;
        if (Config::get('application.multiserver')) {
            $colspan++;
        }
        if (Config::get('application.dstchannel')) {
            $colspan++;
        }
        if (Config::get('application.clid')) {
            $colspan++;
        }
        if (Config::get('application.accountcode')) {
            $colspan++;
        }

        $buttons = Auth::user()->buttons;
        if (!$buttons) {
            $colspan--;
        }

        $this->layout->nest('content', 'cdr.view', array(
            'cdr' => $cdr,
            'related_cdrs' => $related_cdrs,
            'filefield' => $filefield,
            'per_page_options' => $per_page_options,
            'total_billsec' => $total_billsec,
            'colspan' => $colspan,
            'buttons' => $buttons,
        ));


    }

    private static function get_related_uniueids($uniqueid)
    {
        $cels = DB::table('cel')
            ->select(array('uniqueid', 'linkedid'))
            ->where('uniqueid', '=', $uniqueid)
            ->or_where('linkedid', '=', $uniqueid)
            ->get();

        $last_set = array();
        foreach ($cels as $cel) {
            $set = array();
            $set[] = $cel->uniqueid;
            $set[] = $cel->linkedid;
            $set = array_unique($set);
            sort($set);

            if ($last_set === $set) {
                $cels_matching_set = DB::table('cel')
                    ->select(array('uniqueid', 'linkedid'))
                    ->where_in('uniqueid', $set)
                    ->or_where_in('linkedid', $set)
                    ->get();
                $ids = array();
                foreach ($cels_matching_set as $cel_matching_set) {
                    $ids[] = $cel_matching_set->uniqueid;
                    $ids[] = $cel_matching_set->linkedid;
                    $ids = array_unique($ids);
                    sort($ids);
                }

                return $ids;
            }

            $last_set = $set;
        }

        return array();
    }

    private static function get_cdrs_by_uniqueids($uniqueids)
    {
        if (Config::get('application.multiserver')) {
            return DB::table('cdr')->select('*')
                ->where_in('uniqueid', $uniqueids);
        }
        return DB::table('cdr')->select(array(
            '*',
            'users_src.name AS src_name',
            'users_dst.name AS dst_name'
        ))
            ->left_join('asterisk.ringgroups', 'dst', '=', 'asterisk.ringgroups.grpnum')
            ->left_join('asterisk.users AS users_src', 'src', '=', 'users_src.extension')
            ->left_join('asterisk.users AS users_dst', 'dst', '=', 'users_dst.extension')
            ->where_in('uniqueid', $uniqueids);
    }

    public function action_listen($uniqueid, $calldate)
    {
        $html = '<embed src="/wavplayer.swf?gui=full&autoplay=true&h=20&w=300&sound=/cdr/download/' . $uniqueid . '/' . $calldate . '" width="300" height="20" scale="noscale" bgcolor="#dddddd"/>';
        return $html;
    }

    public function action_download($uniqueid, $calldate)
    {
        Config::set('database.default', 'asterisk');
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
