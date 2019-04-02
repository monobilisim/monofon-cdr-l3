<?php

class Tag_Controller extends Base_Controller
{
    public function before()
    {
        parent::before();
        
        // js
        Asset::add('jquery-ui', 'jquery-ui/jquery-ui-1.9.1.custom.min.js');
        Asset::add('jquery-ui-tr', 'jquery-ui/jquery.ui.datetimepicker-tr.js');
        Asset::add('jquery-ui-timepicker', 'jquery-ui/jquery-ui-timepicker-addon.js');
        Asset::add('cdr', 'js/cdr.js');

        // css
        Asset::add('jquery-ui', 'jquery-ui/smoothness/jquery-ui-1.9.1.custom.min.css');
        
        Config::set('database.default', 'asterisk');
    }
    
    private static $title = 'Etiket Raporu';
    
    private static $columns = array(
        'datetime' => 'Tarih ve Saat',
        'queue' => 'Kuyruk',
        'agent' => 'Temsilci',
        'clid' => 'clid',
        'url' => 'url',
        'did' => 'did',
        'position' => 'position',
        'info5' => 'Etiket',
    );
    
    public static function getValue($row, $column)
    {
        $value = $row->$column;
        if ($column == 'datetime') {
            $value = '<a class="cdr-link"
                   href="' . URL::to('cdr/view/'.$row->uniqueid.'/'.strtotime($row->datetime)) . '">' . date('d.m.Y', strtotime($row->datetime)) . ' - ' . date('H:i:s', strtotime($row->datetime)) . '</a>';
        }
        return $value;
    }
    
    public function action_index()
    {
        $datestart = Cdr::format_datetime_input(Input::get('datestart', date('Y-m-d 00:00')));
        $dateend = Cdr::format_datetime_input(Input::get('dateend', date('Y-m-d 23:59')));
        
        $query = DB::table('qstats.queue_stats_mv')
            ->where('event', '=', 'COMPLETECALLER');
        
        $query->raw_where("datetime BETWEEN '$datestart' AND '$dateend'");
        
        // temsilci bazında toplamlar
        $q = clone $query;
        $q->select(array(
            'agent',
            DB::raw("sum(case when info5 != '' then 1 else 0 end) as total_tagged"),
            DB::raw("sum(case when info5 = '' then 1 else 0 end) as total_not_tagged"),
        ));
        $q->group_by('agent');
        $q->order_by('agent');
        $agent_totals = $q->get();
        
        $query->select(array(
            'queue_stats_mv.*',
        ));
        
        $agent = Input::get('agent');
        if ($agent) {
            $query->where('agent', '=', $agent);
        }
        
        $tag = Input::get('tag');
        if ($tag) {
            if ($tag == 'null') {
                $query->where('info5', '=', '');
            }
            else {
                $query->where('info5', '=', $tag);
            }
        }
        
        $q = clone $query;
        $total_tagged = $q->where('info5', '!=', '')->count();

        $default_sort = array(
            'sort' => 'datetime',
            'dir' => 'desc',
        );
        $sort = Input::get('sort', $default_sort['sort']);
        $dir = Input::get('dir', $default_sort['dir']);
        $query->order_by($sort, $dir);
        
        if (isset($_GET['export'])) {
            return self::export_to_excel($query);
        }
        
        $per_page = Input::get('per_page', 10);
        $query = $query->paginate($per_page);

        $query = PaginatorSorter::make($query->results, $query->total, $per_page, $default_sort);
        
        $this->layout->nest('content', 'tag.index', array(
            'title' => self::$title,
            'query' => $query,
            'total_tagged' => $total_tagged,
            'agent_totals' => $agent_totals,
            'per_page_options' => Cdr::$per_page_options,
            'columns' => self::$columns,
            'helper' => $this,
        ));
    }
    
    private static function export_to_excel($query)
    {
        require 'libraries/PHP_XLSXWriter/xlsxwriter.class.php';
        
        $rows = $query->get();
        
        $data = array();
        
        $title_row = array();
        foreach (self::$columns as $column => $column_title) {
            $title_row[] = $column_title;
        }
        $data[] = $title_row;
        
        foreach ($rows as $row) {
            $data_row = array();
            foreach (self::$columns as $column => $column_title) {
                $data_row[] = $row->$column;
            }
            $data[] = $data_row;
        }

        $writer = new XLSXWriter();
        $writer->writeSheet($data);
        
        $filename = self::$title . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename);
        $writer->writeToStdOut();
        exit;
    }
}