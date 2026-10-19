<?php

class Cdr extends Eloquent
{
    public static $table = 'v_cdr';
    public static $key = 'uniqueid';

    public static function format_duration($t)
    {
        if ($t >= 3600) {
            return sprintf("%02d%s%02d%s%02d", floor($t / 3600), ':', ($t / 60) % 60, ':', $t % 60);
        } else {
            return sprintf("%02d%s%02d", ($t / 60) % 60, ':', $t % 60);
        }
    }

    public static function format_agent_billsec($t)
    {
        if (!$t) {
            return '';
        }

        return self::format_duration($t);
    }

    /**
     * Ekranda gösterilecek çağrı durumu.
     *
     * Asterisk'in disposition alanı iki yönde de yanılıyor: aktarma
     * senaryolarında konuşma yapıldığı halde NO ANSWER kalıyor, gelen çağrıyı
     * IVR karşıladığında ise kimse açmasa bile ANSWERED yazıyor. Ölçüt olarak
     * aynı linkedid altında köprülenme olup olmadığı kullanılır; bkz.
     * Cdr_Controller::mark_bridged().
     */
    public static function display_disposition($cdr)
    {
        if (self::has_conversation($cdr)) {
            return 'ANSWERED';
        }

        if ($cdr->disposition !== 'ANSWERED') {
            return $cdr->disposition;
        }

        // Köprülenmemiş çağrıda ANSWERED'ı yazan IVR'dır, kimse açmamıştır.
        // Çağrının nasıl bittiğini lastapp söylüyor: Busy ile biten çağrı
        // FreePBX'in s-BUSY (dahili meşgul), Congestion ile biten ise
        // s-CHANUNAVAIL (dahiliye ulaşılamıyor) koluna düşmüş demektir.
        $lastapp = isset($cdr->lastapp) ? $cdr->lastapp : '';

        if ($lastapp === 'Busy') {
            return 'BUSY';
        }

        if ($lastapp === 'Congestion') {
            return 'FAILED';
        }

        return 'NO ANSWER';
    }

    /**
     * Çağrıda gerçek bir görüşme yapılıp yapılmadığı: tek ölçüt
     * Cdr_Controller::mark_bridged() tarafından doldurulan bridged bayrağıdır.
     */
    public static function has_conversation($cdr)
    {
        // Bayrak hesaplanmadıysa (mark_bridged çağrılmamış) CDR'ın kendi
        // disposition'ına düşülür.
        if (!isset($cdr->bridged)) {
            return $cdr->disposition === 'ANSWERED';
        }

        return (bool) $cdr->bridged;
    }

    /**
     * Satırın kendi konuşma süresi ("Görüşme" sütunu).
     *
     * Görüşme hiç gerçekleşmediyse gösterilmez: gelen çağrıyı IVR
     * cevapladığında billsec, aranan dahili hiç açmasa bile çağrının sistemde
     * geçirdiği süreyi (IVR menüsü + çalma) saymaya başlıyor. O süre zaten
     * "Toplam" sütununda görünüyor.
     */
    public static function format_talk_duration($cdr)
    {
        if (!self::has_conversation($cdr)) {
            return '—';
        }

        return self::format_duration(self::talk_seconds($cdr));
    }

    /**
     * Görüşmenin saniye cinsinden uzunluğu.
     *
     * Ölçüt CEL'den hesaplanan köprülenme süresidir (bkz.
     * Cdr_Controller::mark_bridged): billsec, gelen çağrıyı IVR karşıladığında
     * konuşma başlamadan işlemeye başlıyor ve kuyrukta/IVR'da geçen süreyi de
     * sayıyor — ölçülen bir çağrıda billsec 143 sn iken gerçek görüşme 13 sn.
     * Ses kaydı da (MixMonitor "b" seçeneği) yalnızca köprülüyken yazıldığı
     * için bu değer oynatıcıda görünen süreyle örtüşür.
     *
     * Köprülenme süresi hesaplanamadıysa (mark_bridged çağrılmamış ya da CEL'de
     * kanalın çıkışı görünmüyor) billsec'e düşülür.
     */
    public static function talk_seconds($cdr)
    {
        if (isset($cdr->bridge_seconds)) {
            return (int) $cdr->bridge_seconds;
        }

        return (int) $cdr->billsec;
    }

    public static function format_src_dst($cdr, $type)
    {
        $name = $type . '_name';

        if (!isset($cdr->$name)) {
            if ($type == 'dst' && $cdr->dst == 's') {
                return 'Santral';
            } else {
                return $cdr->$type;
            }
        }

        if ($cdr->$name) {
            return $cdr->$name . ' (' . $cdr->$type . ')';
        } elseif ($type == 'dst' and $cdr->description) {
            return $cdr->description . ' (' . $cdr->$type . ')';
        } else {
            return $cdr->$type;
        }
    }

    /**
     * Çağrıyı gerçekten alan dahiliyi veren SQL ifadesi: Dial ile bir dahili
     * kanalına çıkıldıysa kanaldaki dahili, aksi halde çevrilen numara.
     * Sadece tamamı rakamlardan oluşan peer adları (PJSIP/6000-000563aa gibi)
     * dahili sayılır; trunk kanalları (PJSIP/voip1-000563aa) hariç tutulur.
     * Sorgularda `dst_real` olarak seçilir; filtrelerde de aynı ifade kullanılır.
     */
    public static function dst_real_sql()
    {
        return "
            CASE
                WHEN lastapp = 'Dial'
                    AND dstchannel REGEXP '^(PJSIP|SIP)/[0-9]+(-|$)'
                THEN SUBSTRING_INDEX(
                    SUBSTRING_INDEX(dstchannel, '/', -1),
                    '-',
                    1
                )
                ELSE dst
            END";
    }

    /**
     * Aranan kolonu. Çevrilen numara ile çağrıyı gerçekten alan dahili farklıysa
     * (ring group, kuyruk, yönlendirme) ikisini ok ile birlikte gösterir.
     */
    public static function format_dst($cdr)
    {
        if (!isset($cdr->dst_real) or $cdr->dst_real == $cdr->dst) {
            return self::format_src_dst($cdr, 'dst');
        }

        // Ring group açıklaması çevrilen numaraya, kullanıcı adı ise
        // çağrıyı alan dahiliye ait.
        $dialed = self::format_number($cdr->dst, isset($cdr->description) ? $cdr->description : null);
        $real   = self::format_number($cdr->dst_real, isset($cdr->dst_name) ? $cdr->dst_name : null);

        return $dialed . ' → ' . $real;
    }

    protected static function format_number($number, $label = null)
    {
        if ($number === 's') {
            return 'Santral';
        }

        return $label ? $label . ' (' . $number . ')' : $number;
    }

    public static function format_clid($clid)
    {
        preg_match('/"(.+)"/', $clid, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }

    public static function get_options($name)
    {
        if ($name == 'did') {
            $rows = DB::table('cdr')->select('did')->distinct()->get();
            foreach ($rows as $row) {
                $options[$row->did] = $row->did;
            }
        }

        if ($name == 'ringgroup') {
            $options = array('' => '');
            $ringgroups = DB::table('asterisk.ringgroups')->get();
            foreach ($ringgroups as $rg) {
                $options[$rg->grpnum] = $rg->grpnum . ' / ' . $rg->description;
            }
        }

        if ($name == 'status') {
            $options = array(
                '' => '',
                'ANSWERED'   => __('misc.answered'),
                'NO ANSWER'  => __('misc.no answer'),
                'FAILED'     => __('misc.failed'),
                'BUSY'       => __('misc.busy'),
            );
        }

        if ($name == 'scope') {
            $options = array(
                '' => '',
                'in' => 'Dahili Aramalar',
                'out' => 'Dış Aramalar',
            );
        }

        if ($name == 'tag' || $name == 'tag_update') {
            static $call_tags = null;
            if ($call_tags === null) {
                $tagQueueCalls = parse_ini_file('/var/www/html/fop2/admin/plugins/tagQueueCalls/tagQueueCalls.ini');
                $call_tags = explode('|', $tagQueueCalls['call_tags']);
            }
            $options = array('' => '');
            if ($name != 'tag_update') {
                $options['null'] = '-- BOŞ --';
            }
            foreach ($call_tags as $call_tag) {
                $options[$call_tag] = $call_tag;
            }
        }

        if ($name == 'agent') {
            $options = array('' => '');
            $users = DB::table('asterisk.users')->select(array('extension', 'name'))->order_by('extension')->get();
            foreach ($users as $user) {
                $options[$user->name] = $user->name;
            }
        } elseif ($name == 'note') {
            $options = array(
                    '' => '',
                    'yes'   => 'Not eklenmiş',
                    'no'  => 'Not eklenmemiş',
            );
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
        } else {
            $url .= '?export';
        }
        return $url;
    }

    public static function getTemporaryOggDir()
    {
        return path('storage') . 'tmp';
    }

    public static $monitor_dir = '/var/spool/asterisk/monitor';

    // boş (sessiz) ses kayıtları bu boyutta oluşuyor, bu boyutu aşmayan kayıtları yok sayıyoruz
    public static $recording_min_size = 2693;

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
        // cdr tablosunda bazı satırlarda filefield sütunu dosya uzantısı içermiyor, eğer öyleyse uzantıyı ekleyelim
        $ext = Config::get('application.extension');
        if (preg_match('/\.[a-zA-Z]{3}$/', $file['name']) === 0) {
            $file['name'] .= ".$ext";
        }
        return $file;
    }

    public static function has_recording($cdr)
    {
        $filefield = Config::get('application.filefield');

        if (!$cdr->$filefield) {
            return false;
        }

        $size = self::recording_size($cdr);

        // boyut tespit edilemediyse (uzak sunucuya erişilemiyor vb.) kaydı var sayalım
        if ($size === null) {
            return true;
        }

        return $size > self::$recording_min_size;
    }

    // dosya boyutunu byte olarak döndürür, dosya yoksa 0, boyut tespit edilemiyorsa null
    public static function recording_size($cdr)
    {
        $file = self::retrieve_file($cdr);
        $abs_path = self::$monitor_dir . '/' . $file['path'] . '/' . $file['name'];

        if (file_exists($abs_path)) {
            return (int) filesize($abs_path);
        }

        if (!Config::get('application.remote_base_url')) {
            return 0;
        }

        return self::remote_file_size($file);
    }

    /**
     * Uzak depodaki (Versity Gateway, Minio vb. S3 uyumlu) dosyanın URL'i; uzak
     * depo tanımlı değilse null. Erişim anahtarı tanımlıysa URL SigV4 ile
     * imzalanır (private bucket). İmza metoda bağlı olduğundan HEAD isteği için
     * $method = 'HEAD' verilmeli.
     */
    public static function remote_url($file, $method = 'GET')
    {
        $remote_base_url = Config::get('application.remote_base_url');
        if (!$remote_base_url) {
            return null;
        }

        // bucket ayrı tanımlıysa yola eklenir; tanımlı değilse base URL bucket'ı içeriyordur
        $bucket = trim((string) Config::get('application.remote_bucket'), '/');
        $key = ($bucket !== '' ? $bucket . '/' : '') . ($file['path'] !== '' ? $file['path'] . '/' : '') . $file['name'];
        $url = rtrim($remote_base_url, '/') . '/' . S3Presigner::encode_path($key);

        if (!Config::get('application.remote_key')) {
            return $url;
        }

        $presigner = new S3Presigner(Config::get('application.remote_key'), Config::get('application.remote_secret'), Config::get('application.remote_region', 'us-east-1'));
        return $presigner->presign($url, $method, (int) Config::get('application.remote_url_ttl', 300));
    }

    private static function remote_file_size($file)
    {
        static $sizes = array();

        // imzalı URL her istekte değiştiği için önbellek anahtarı dosya yolu
        $cache_key = $file['path'] . '/' . $file['name'];
        if (array_key_exists($cache_key, $sizes)) {
            return $sizes[$cache_key];
        }

        stream_context_set_default(array('http' => array('method' => 'HEAD', 'timeout' => 3)));
        $headers = @get_headers(self::remote_url($file, 'HEAD'), 1);
        stream_context_set_default(array('http' => array('method' => 'GET')));

        $sizes[$cache_key] = self::size_from_headers($headers);
        return $sizes[$cache_key];
    }

    /**
     * get_headers() çıktısından dosya boyutu: 2xx ise Content-Length, dosya yok
     * (404) ya da erişim reddedildi (403; ListBucket yetkisi yoksa S3 olmayan
     * dosya için de 403 döner) ise 0, tespit edilemiyorsa null.
     */
    public static function size_from_headers($headers)
    {
        if (!$headers) {
            return null;
        }

        // yönlendirme varsa birden fazla durum satırı olur, sonuncusu geçerli
        $status = null;
        foreach ($headers as $k => $v) {
            if (is_int($k) && preg_match('#^HTTP/\S+\s+(\d{3})#', $v, $m)) {
                $status = (int) $m[1];
            }
        }

        if ($status === 403 || $status === 404) {
            return 0;
        }
        if ($status === null || $status < 200 || $status >= 300) {
            return null;
        }

        $length = isset($headers['Content-Length']) ? $headers['Content-Length'] : (isset($headers['content-length']) ? $headers['content-length'] : null);
        if (is_array($length)) {
            $length = end($length);
        }

        return $length !== null ? (int) $length : null;
    }
}
