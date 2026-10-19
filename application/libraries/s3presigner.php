<?php

/**
 * S3 uyumlu depolar (Versity Gateway, Minio, AWS S3) için SigV4 query-string
 * imzalı (presigned) URL üretir. AWS SDK'ya bağımlı değildir; L12'deki
 * app/Support/S3Presigner.php ile birebir aynı algoritma.
 *
 * $base_url bucket'ı da içeren path-style adrestir: http://10.10.10.10:7070/bucket
 */
class S3Presigner
{
    private $key;
    private $secret;
    private $region;

    public function __construct($key, $secret, $region = 'us-east-1')
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->region = $region;
    }

    /**
     * @param  string  $url  imzalanacak tam URL (yol segmentleri zaten encode edilmiş)
     * @param  string  $method  imza HTTP metoduna bağlıdır: GET ve HEAD için ayrı URL gerekir
     * @param  int  $expires  saniye cinsinden geçerlilik süresi
     * @param  int|null  $time  test için sabit zaman
     */
    public function presign($url, $method = 'GET', $expires = 300, $time = null)
    {
        $parts = parse_url($url);
        $host = $parts['host'];
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }
        $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';

        $time = $time === null ? time() : $time;
        $amz_date = gmdate('Ymd\THis\Z', $time);
        $date = gmdate('Ymd', $time);
        $scope = $date . '/' . $this->region . '/s3/aws4_request';

        $query = array(
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->key . '/' . $scope,
            'X-Amz-Date' => $amz_date,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        );
        ksort($query);
        $canonical_query = self::build_query($query);

        $canonical_request = implode("\n", array(
            strtoupper($method),
            $path,
            $canonical_query,
            'host:' . $host,
            '',
            'host',
            'UNSIGNED-PAYLOAD',
        ));

        $string_to_sign = implode("\n", array(
            'AWS4-HMAC-SHA256',
            $amz_date,
            $scope,
            hash('sha256', $canonical_request),
        ));

        $signing_key = hash_hmac('sha256', $date, 'AWS4' . $this->secret, true);
        $signing_key = hash_hmac('sha256', $this->region, $signing_key, true);
        $signing_key = hash_hmac('sha256', 's3', $signing_key, true);
        $signing_key = hash_hmac('sha256', 'aws4_request', $signing_key, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        return $parts['scheme'] . '://' . $host . $path . '?' . $canonical_query . '&X-Amz-Signature=' . $signature;
    }

    /**
     * Nesne anahtarını URL yoluna çevirir: segmentler RFC 3986'ya göre encode
     * edilir, '/' korunur (S3 yolu tek kez encode edilmiş bekler).
     */
    public static function encode_path($path)
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private static function build_query(array $query)
    {
        $pairs = array();
        foreach ($query as $k => $v) {
            $pairs[] = rawurlencode($k) . '=' . rawurlencode($v);
        }

        return implode('&', $pairs);
    }
}
