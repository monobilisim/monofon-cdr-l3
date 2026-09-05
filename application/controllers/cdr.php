<?php

class SimpleCollection implements IteratorAggregate, Countable
{
    protected $items;

    public function __construct(array $items = array())
    {
        $this->items = $items;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    public function count()
    {
        return count($this->items);
    }

    public function all()
    {
        return $this->items;
    }

    public function first()
    {
        return isset($this->items[0]) ? $this->items[0] : null;
    }

    public function sum($key)
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += is_object($item) ? $item->$key : $item[$key];
        }
        return $total;
    }

    public function pluck($key)
    {
        $result = array();
        foreach ($this->items as $item) {
            $result[] = is_object($item) ? $item->$key : $item[$key];
        }
        return $result;
    }

    public function push($item)
    {
        $this->items[] = $item;
        return $this;
    }

    public function order_by($key, $direction = 'asc')
    {
        usort($this->items, function ($a, $b) use ($key, $direction) {
            $valA = is_object($a) ? $a->$key : $a[$key];
            $valB = is_object($b) ? $b->$key : $b[$key];

            if ($valA == $valB) return 0;

            $result = ($valA < $valB) ? -1 : 1;
            return ($direction === 'desc') ? -$result : $result;
        });

        return $this;
    }

    public function __get($name)
    {
        if ($name === 'results' || $name === 'items') {
            return $this->items;
        }
        return null;
    }
}

class Cdr_Controller extends Base_Controller
{
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

        // =========================================================================
        // 1. QUERY TEMPLATES — the shape of the SQL, with placeholders for filters
        // =========================================================================

        $innerWhereSql  = '__INNER_WHERE__';   // filters applied inside the ranking subquery
        $outerWhereSql  = '__OUTER_WHERE__';   // filters applied after joins
        $tagSelect      = '__TAG_SELECT__';    // optional ", queue_log.data1 AS tag"
        $tagJoin        = '__TAG_JOIN__';      // optional LEFT JOIN queue_log
        $orderBySql     = '__ORDER_BY__';      // "ranked.col DIR"

        // Alias WHERE'de görünmediği için ifadenin kendisi filtrelere de veriliyor.
        $dstRealExpr = Cdr::dst_real_sql();

        $baseSql = "
            FROM (
                SELECT
                    v_cdr.*,

                    $dstRealExpr AS dst_real,

                    ROW_NUMBER() OVER (
                        PARTITION BY linkedid
                        ORDER BY
                            recordingfile != '' DESC,
                            billsec DESC,
                            duration DESC,
                            calldate DESC,
                            uniqueid ASC
                    ) AS rn
                FROM v_cdr
                WHERE $innerWhereSql
            ) ranked
            LEFT JOIN asterisk.ringgroups ON ranked.dst = asterisk.ringgroups.grpnum
            LEFT JOIN asterisk.users AS users_src ON ranked.src = users_src.extension
            LEFT JOIN asterisk.users AS users_dst ON ranked.dst_real = users_dst.extension
            LEFT JOIN cdrapp.notes AS notes ON ranked.uniqueid = notes.uniqueid
            $tagJoin
            WHERE $outerWhereSql
        ";

        $totalsSqlTemplate = "
            SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(ranked.billsec), 0) AS total_billsec,
                COALESCE(SUM(ranked.duration), 0) AS total_duration
            $baseSql
        ";

        // ranked.* zaten hem dst (çevrilen numara) hem dst_real (çağrıyı alan
        // dahili) alanlarını taşıyor; gösterimi Cdr::format_dst() birleştiriyor.
        $dataSqlTemplate = "
            SELECT ranked.*,
                asterisk.ringgroups.description,
                users_src.name AS src_name,
                users_dst.name AS dst_name,
                notes.note
                $tagSelect
            $baseSql
            ORDER BY $orderBySql
            LIMIT ? OFFSET ?
        ";

        $exportSqlTemplate = "
            SELECT ranked.*,
                asterisk.ringgroups.description,
                users_src.name AS src_name,
                users_dst.name AS dst_name,
                notes.note
                $tagSelect
            $baseSql
            ORDER BY $orderBySql
        ";

        // =========================================================================
        // 2. FILTER BUILDING — collect WHERE clauses and bindings from request
        // =========================================================================

        // --- Inner filters (apply to v_cdr columns, before ranking) ---
        $innerClauses = array('v_cdr.calldate BETWEEN ? AND ?');
        $innerBindings = array($datestart, $dateend);

        if (!Auth::user()->allrows) {
            $innerClauses[] = "v_cdr.$filefield != ?";
            $innerBindings[] = '';
        }
        if (!empty($status)) {
            $innerClauses[] = 'v_cdr.disposition = ?';
            $innerBindings[] = $status;
        }
        if (!empty($dstchannel)) {
            $innerClauses[] = 'v_cdr.dstchannel LIKE ?';
            $innerBindings[] = "%$dstchannel%";
        }
        if (!empty($accountcode)) {
            $innerClauses[] = 'v_cdr.accountcode LIKE ?';
            $innerBindings[] = "%$accountcode%";
        }
        if (!empty($did)) {
            $innerClauses[] = 'v_cdr.did = ?';
            $innerBindings[] = $did;
        }

        // Number filters (raw SQL fragments from existing helper)
        $number_filters = array();
        if (Auth::user()->perm)   $number_filters['perm']    = Auth::user()->perm;
        if (!empty($src))         $number_filters['src']     = $src;
        if (!empty($dst))         $number_filters['dst']     = $dst;
        if (!empty($src_dst))     $number_filters['src_dst'] = $src_dst;
        foreach ($number_filters as $type => $val) {
            $innerClauses[] = self::build_number_where_clauses($type, $val, $dstRealExpr);
        }

        // Scope filter
        if (!empty($scope)) {
            if ($scope == 'in') {
                $innerClauses[] = '(CHAR_LENGTH(v_cdr.src) < 7 AND CHAR_LENGTH(v_cdr.dst) < 7)';
            } elseif ($scope == 'out') {
                $innerClauses[] = '(CHAR_LENGTH(v_cdr.src) >= 7 OR CHAR_LENGTH(v_cdr.dst) >= 7)';
            }
        }

        // --- Outer filters (apply after joins, on joined tables) ---
        $outerClauses = array('ranked.rn = 1');
        $outerBindings = array();

        if (!empty($tag)) {
            if ($tag == 'null') {
                $outerClauses[] = 'queue_log.data1 IS NULL';
            } else {
                $outerClauses[] = 'queue_log.data1 = ?';
                $outerBindings[] = $tag . Cdr::$tag_suffix;
            }
        }

        if (!empty($note)) {
            if ($note == 'no')  $outerClauses[] = 'notes.note IS NULL';
            if ($note == 'yes') $outerClauses[] = 'notes.note IS NOT NULL';
        }

        // --- Optional tag join/select ---
        $tagSelectSql = '';
        $tagJoinSql = '';
        if (Config::get('application.call_tags')) {
            $tagSelectSql = ', queue_log.data1 AS tag';
            $tagJoinSql = "
                LEFT JOIN asteriskrealtime.queue_log
                    ON queue_log.callid = ranked.linkedid
                AND queue_log.event = 'UPDATEFIELD'
            ";
        }

        // --- Sort column (whitelisted) ---
        $allowedSorts = array(
            'calldate', 'src', 'dst', 'duration', 'billsec',
            'disposition', 'dstchannel', 'clid', 'linkedid',
        );
        $sortCol = in_array($sort, $allowedSorts) ? $sort : 'calldate';
        $sortDir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        // =========================================================================
        // 3. ASSEMBLE — plug filters into templates
        // =========================================================================

        $replacements = array(
            '__INNER_WHERE__' => implode(' AND ', $innerClauses),
            '__OUTER_WHERE__' => implode(' AND ', $outerClauses),
            '__TAG_SELECT__'  => $tagSelectSql,
            '__TAG_JOIN__'    => $tagJoinSql,
            '__ORDER_BY__'    => "ranked.$sortCol $sortDir",
        );

        $totalsSql = strtr($totalsSqlTemplate, $replacements);
        $dataSql   = strtr($dataSqlTemplate,   $replacements);
        $exportSql = strtr($exportSqlTemplate, $replacements);

        $filterBindings = array_merge($innerBindings, $outerBindings);

        // =========================================================================
        // 4. EXECUTE
        // =========================================================================

        // Totals
        $totalsResult  = DB::query($totalsSql, $filterBindings);
        $total         = $totalsResult[0]->total_count;
        $total_billsec = $totalsResult[0]->total_billsec;
        $total_duration = $totalsResult[0]->total_duration;

        // Export branch (no pagination, returns everything)
        if (isset($export)) {
            self::export_to_excel_raw($exportSql, $filterBindings);
        }

        // Paginated data
        $page   = Paginator::page($total, $per_page);
        $offset = ($page - 1) * $per_page;

        $dataBindings = array_merge($filterBindings, array((int) $per_page, (int) $offset));
        $results = DB::query($dataSql, $dataBindings);

        self::mark_bridged($results);

        $cdrs = PaginatorSorter::make($results, $total, $per_page, $default_sort);

        $display_agent_billsec = false;
        if (Config::get('application.agent_billsec')) {
            $display_agent_billsec = true;
            foreach ($results as $result) {
                $result->agent_billsec = self::calculate_agent_billsec($result->linkedid);
            }
        }

        $this->layout->nest('content', 'cdr.index', array(
            'cdrs' => $cdrs,
            'filefield' => $filefield,
            'per_page_options' => Cdr::$per_page_options,
            'total_billsec' => $total_billsec,
            'total_duration' => $total_duration,
            'buttons_download' => Auth::user()->buttons_download,
            'buttons_listen' => Auth::user()->buttons_listen,
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

    protected static function build_number_where_clauses($type, $number_filter, $dst_expr = 'dst')
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

        // Aranan tarafı için hem ham dst hem de gerçekten çalan dahili (dst_real)
        // denenir; ring group / kuyruk çağrılarında listede görünen numara ikincisi.
        $dst_targets = array('dst');
        if ($dst_expr !== 'dst') {
            $dst_targets[] = $dst_expr;
        }

        foreach ($clauses as $key => $clause) {
            $dst_parts = array();
            foreach ($dst_targets as $target) {
                $dst_parts[] = $target . ' ' . $clause;
            }
            $dst_clause = implode(' OR ', $dst_parts);

            if ($type == 'perm' or $type == 'src_dst') {
                $clauses[$key] = 'src ' . $clause . ' OR ' . $dst_clause;
            }
            if ($type == 'src') {
                $clauses[$key] = 'src ' . $clause;
            }
            if ($type == 'dst') {
                $clauses[$key] = $dst_clause;
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

        $related_cdrs = self::get_related_cdrs($uniqueid);
        $related_cels = self::get_cels_by_linkedid($cdr->linkedid)->get();

        // get_queue_logs_by_linkedid() varsayılan bağlantıyı asteriskrealtime'a
        // çevirdiği için CEL kontrolü ondan önce yapılmalı.
        self::mark_bridged($related_cdrs->results);

        $related_queue_logs = self::get_queue_logs_by_linkedid($cdr->linkedid)->get();

        $total_billsec = $related_cdrs->sum('billsec');
        $total_duration = $related_cdrs->sum('duration');

        // SimpleCollection'da total diye bir alan yok; sayfalayıcıya null
        // geçilince "Toplam arama sayısı" boş kalıyordu.
        $related_total = count($related_cdrs);

        $related_cdrs->order_by($sort, $dir);

        $related_cdrs = PaginatorSorter::make($related_cdrs->results, $related_total, $per_page, $default_sort);

        $note = '';
        if (Config::get('application.note')) {
            Config::set('database.default', 'mysql');
            $note = Note::where('uniqueid', '=', $cdr->uniqueid)->first();
            if ($note) {
                $note = $note->note;
            }
        }

        $this->layout->nest('content', 'cdr.view', array(
            'cdr' => $cdr,
            'cdrs' => $related_cdrs,
            'cels' => $related_cels,
            'queue_logs' => $related_queue_logs,
            'filefield' => $filefield,
            'per_page_options' => Cdr::$per_page_options,
            'total_billsec' => $total_billsec,
            'total_duration' => $total_duration,
            'buttons_download' => Auth::user()->buttons_download,
            'buttons_listen' => Auth::user()->buttons_listen,
            'display_agent_billsec' => false,
            'note' => $note,
        ));
    }

    /**
     * Çağrının gerçekten köprülendiği (yani konuşma yapıldığı) satırları
     * işaretler. Aktarma güdüklerinde CDR satırı NO ANSWER kalır ama aynı
     * linkedid altında CEL'de BRIDGE_ENTER olayı bulunur.
     *
     * Ölçüt olarak billsec ya da CEL ANSWER kullanılamaz: gelen çağrıyı IVR
     * cevapladığında (AGI) ikisi de işlemeye başlıyor, aranan dahili hiç
     * açmasa bile. İki kanalın köprülenmesi ise ancak gerçek görüşmede olur.
     *
     * Sayfa başına tek sorgu atar ve cel.linkedid_index üzerinden çalışır.
     * Varsayılan bağlantıyı değiştiren sorgulardan (queue_log) ÖNCE çağrılmalı.
     */
    private static function mark_bridged($rows)
    {
        $linkedids = array();

        foreach ($rows as $row) {
            $row->bridged = false;

            if ($row->disposition !== 'ANSWERED' && !empty($row->linkedid)) {
                $linkedids[$row->linkedid] = true;
            }
        }

        if (!$linkedids) {
            return;
        }

        // Excel çıktısında sayfalama olmadığı için liste çok uzun olabiliyor;
        // IN listesini parçalara bölüyoruz.
        $bridged = array();
        foreach (array_chunk(array_keys($linkedids), 1000) as $chunk) {
            $cels = DB::table('cel')
                ->where('eventtype', '=', 'BRIDGE_ENTER')
                ->where_in('linkedid', $chunk)
                ->get(array('linkedid'));

            foreach ($cels as $cel) {
                $bridged[$cel->linkedid] = true;
            }
        }

        foreach ($rows as $row) {
            if (isset($bridged[$row->linkedid])) {
                $row->bridged = true;
            }
        }
    }

    private static function calculate_agent_billsec($linkedid)
    {
        $related_cels = self::get_cels_by_linkedid($linkedid)->get();

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

    private static function get_related_uniqueids($linkedid = 'dev')
    {
        $result = DB::table('cel')
            ->where('linkedid', '=', $linkedid)
            ->get();

        $uniqueids = array();
        foreach ($result as $row) {
            $uniqueids[] = $row->uniqueid;
        }
        $uniqueids = array_unique($uniqueids);
        sort($uniqueids);
        return $uniqueids;
    }

    private static function get_related_cdrs($uniqueid)
    {
        // bağlı çağrı aramasında çağrı saatinin öncesine ve sonrasına bakılacak süre (saniye)
        $related_window = 300;

        $seedRow = DB::table('cdr')->where('uniqueid', '=', $uniqueid)->first();

        if (!$seedRow) {
            return new SimpleCollection();
        }

        $collected = array();
        $processedLinkedIds = array();
        $queue = array($seedRow->linkedid);

        for ($hop = 0; $hop < 5 && count($queue) > 0; $hop++) {
            $currentLinkedId = array_shift($queue);

            if (in_array($currentLinkedId, $processedLinkedIds)) {
                continue;
            }
            $processedLinkedIds[] = $currentLinkedId;

            $group = DB::table('cdr')
                ->where('linkedid', '=', $currentLinkedId)
                ->get(array('*', DB::raw(Cdr::dst_real_sql() . ' AS dst_real')));

            foreach ($group as $row) {
                $collected[] = $row;

                if (empty($row->dstchannel)) {
                    continue;
                }

                // aynı kanal üzerindeki bağlı çağrıları çağrı saatinin 5 dk. öncesi ve sonrası içinde arıyoruz
                $window_start = date('Y-m-d H:i:s', strtotime($row->calldate) - $related_window);
                $window_end = date('Y-m-d H:i:s', strtotime($row->calldate) + $related_window);

                $bridgedRows = DB::table('cdr')
                    ->where('dstchannel', '=', $row->dstchannel)
                    ->where('calldate', '>=', $window_start)
                    ->where('calldate', '<=', $window_end)
                    ->where('uniqueid', '!=', $row->uniqueid)
                    ->get();

                foreach ($bridgedRows as $bridged) {
                    if (!in_array($bridged->linkedid, $processedLinkedIds)
                        && !in_array($bridged->linkedid, $queue)) {
                        $queue[] = $bridged->linkedid;
                    }
                }
            }
        }

        $seen = array();
        $result = array();

        // Aynı recordingfile'ı paylaşan satırlardan hangisinin temsilci olacağı,
        // ana liste sorgusundaki ROW_NUMBER sıralamasıyla birebir aynı olmalı;
        // aksi halde iki tablo aynı çağrı için farklı disposition ve süre gösterir.
        usort($collected, function ($a, $b) {
            $a_rec = empty($a->recordingfile) ? 0 : 1;
            $b_rec = empty($b->recordingfile) ? 0 : 1;
            if ($a_rec !== $b_rec) {
                return $b_rec - $a_rec;
            }
            if ((int) $a->billsec !== (int) $b->billsec) {
                return (int) $b->billsec - (int) $a->billsec;
            }
            if ((int) $a->duration !== (int) $b->duration) {
                return (int) $b->duration - (int) $a->duration;
            }
            $cmp = strcmp($b->calldate, $a->calldate);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a->uniqueid, $b->uniqueid);
        });

        foreach ($collected as $row) {
            if (empty($row->recordingfile) || isset($seen[$row->recordingfile])) {
                continue;
            }
            $seen[$row->recordingfile] = true;
            $result[] = $row;
        }

        usort($result, function ($a, $b) {
            return strcmp($a->calldate, $b->calldate);
        });

        return new SimpleCollection($result);
    }

    private static function get_cels_by_linkedid($linkedid)
    {
        return DB::table('cel')->where('linkedid', '=', $linkedid);
    }

    private static function get_queue_logs_by_linkedid($linkedid)
    {
        $uniqueids = self::get_related_uniqueids($linkedid);

        Config::set('database.default', 'asteriskrealtime');

        return DB::table('queue_log')->select('*')
            ->where_in('callid', $uniqueids);
    }

    public function action_listen($uniqueid, $calldate)
    {
        $download_url = URL::to('/cdr/download/' . $uniqueid . '/' . $calldate);

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

                wavesurfer.load('$download_url');

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

        return $html;
    }

    public function action_download($uniqueid, $calldate)
    {
        $cdr = Cdr::where('uniqueid', '=', $uniqueid)->where('calldate', '=', date('Y-m-d H:i:s', $calldate))->first();
        $file = Cdr::retrieve_file($cdr);

        $abs_path = Cdr::$monitor_dir . '/' . $file['path'] . '/' . $file['name'];

        $file_url = null;
        if (!file_exists('file://' . $abs_path)) {
            $remote_base_url = Config::get('application.remote_base_url');
            $file_url = $remote_base_url . '/' . $file['path'] . '/' . urlencode($file['name']);
        }

        $file_info = pathinfo($file['name']);
        if ($file_info['extension'] == 'WAV') {
            // ses kaydı türü wav ise önce ogg'ye dönüştürelim
            $temp_path = Cdr::getTemporaryOggDir();
            if (!file_exists($temp_path)) {
                mkdir($temp_path, 0755);
            }
            if ($file_url) {
                $abs_path = $temp_path . '/' . $file['name'];
                file_put_contents($abs_path, fopen($file_url, 'r'));
            }
            $file['name'] = str_replace('.WAV', '.ogg', $file['name']);
            $abs_path_ogg = $temp_path . '/' . $file['name'];
            exec('asterisk -rx "file convert ' . $abs_path . ' ' . $abs_path_ogg . '"');
            return Response::download($abs_path_ogg, $file['name']);
        } else {
            if ($file_url) {
                header('Content-Transfer-Encoding: Binary');
                header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
                readfile($file_url);
            } else {
                return Response::download($abs_path, $file['name']);
            }
        }
    }

    /**
     * Ham SQL sonucundan Excel çıktısı üretir.
     *
     * Liste sorgusu ham SQL'e taşındığında bu fonksiyon eksik kalmış, çağrısı
     * yazılmış ama gövdesi yazılmamıştı; eski export_to_excel() bir query
     * nesnesi beklediği için kullanılamıyor.
     */
    private static function export_to_excel_raw($sql, $bindings)
    {
        require 'libraries/xlsxwriter.class.php';

        $columns = array(
            'calldate'    => 'Tarih - Saat',
            'did'         => 'DID',
            'clid'        => 'Arayan Tanımı',
            'src'         => 'Arayan',
            'dst'         => 'Aranan',
            'dstchannel'  => 'Aranan Kanal',
            'accountcode' => 'Hesap Kodu',
            'disposition' => 'Durum',
            'billsec'     => 'Görüşme',
            'duration'    => 'Toplam',
        );

        foreach (array('did', 'clid', 'dstchannel', 'accountcode') as $col) {
            if (!Config::get("application.$col")) {
                unset($columns[$col]);
            }
        }

        $cdrs = DB::query($sql, $bindings);

        self::mark_bridged($cdrs);

        $data = array(array_values($columns));

        foreach ($cdrs as $cdr) {
            $data_row = array();

            foreach ($columns as $column => $column_title) {
                if ($column == 'dst') {
                    $value = Cdr::format_dst($cdr);
                } elseif ($column == 'src') {
                    $value = Cdr::format_src_dst($cdr, 'src');
                } elseif ($column == 'disposition') {
                    $value = Lang::line('misc.' . Cdr::display_disposition($cdr))->get();
                } elseif ($column == 'billsec') {
                    $value = Cdr::format_talk_duration($cdr);
                } elseif ($column == 'duration') {
                    $value = Cdr::format_duration($cdr->duration);
                } else {
                    // did ve accountcode v_cdr'de yok; kolon açıksa boş geçilir.
                    $value = isset($cdr->$column) ? $cdr->$column : '';
                }

                $data_row[] = $value;
            }

            $data[] = $data_row;
        }

        $writer = new XLSXWriter();
        $writer->writeSheet($data);

        $filename = 'Çağrı Kayıtları.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $writer->writeToStdOut();
        exit;
    }

    private static function export_to_excel($query)
    {
        require 'libraries/xlsxwriter.class.php';

        $columns = array(
            'calldate' => 'Tarih - Saat',
            'did' => 'DID',
            'clid' => 'Arayan Tanımı',
            'src' => 'Arayan',
            'dst' => 'Aranan',
            'dstchannel' => 'Aranan Kanal',
            'server' => 'Hesap Kodu',
            'disposition' => 'Durum',
            'duration' => 'Süre',
        );

        $config_cols = array('did', 'clid', 'dstchannel', 'accountcode');
        foreach ($config_cols as $col) {
            if (!Config::get("application.$col")) {
                unset($columns[$col]);
            }
        }
        if (!Config::get('application.accountcode')) {
            unset($columns['server']);
        }

        $cdrs = $query->get();

        $data = array();

        $title_row = array();
        foreach ($columns as $column_title) {
            $title_row[] = $column_title;
        }
        $data[] = $title_row;

        foreach ($cdrs as $cdr) {
            $data_row = array();
            foreach ($columns as $column => $column_title) {
                if (in_array($column, array('src', 'dst'))) {
                    $value = $column == 'dst'
                        ? Cdr::format_dst($cdr)
                        : Cdr::format_src_dst($cdr, $column);
                } else if ($column == 'disposition') {
                    $value = Lang::line("misc.$cdr->disposition")->get();
                } else if ($column == 'duration') {
                    $value = Cdr::format_duration($cdr->duration);
                } else {
                    $value = $cdr->$column;
                }
                $data_row[] = $value;
            }
            $data[] = $data_row;
        }

        $writer = new XLSXWriter();
        $writer->writeSheet($data);

        $filename = 'Çağrı Kayıtları.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename);
        $writer->writeToStdOut();
        exit;
    }
}
