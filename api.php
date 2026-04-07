<?php

declare(strict_types=1);

// Suppress error output to prevent path/info leaks
ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/config.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . CACHE_TTL);

/**
 * Fetch URL with cURL and return the response body, or null on failure.
 */
function fetchUrl(string $url, int $timeout = 10): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'MeteoSynin/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        return null;
    }

    return $body;
}

/**
 * Read from file cache if fresh, otherwise return null.
 */
function cacheRead(string $key): ?array
{
    $file = CACHE_DIR . '/' . $key . '.json';
    if (!is_file($file)) {
        return null;
    }
    $mtime = filemtime($file);
    if ($mtime === false || (time() - $mtime) > CACHE_TTL) {
        return null;
    }
    $data = file_get_contents($file);
    if ($data === false) {
        return null;
    }
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Write data to file cache.
 */
function cacheWrite(string $key, array $data): void
{
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0750, true);
    }
    $file = CACHE_DIR . '/' . $key . '.json';
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (file_put_contents($tmp, json_encode($data), LOCK_EX) !== false) {
        rename($tmp, $file);
    }
}

/**
 * Convert Fahrenheit to Celsius.
 */
function fToC(?string $f): ?float
{
    if ($f === null) return null;
    return round(((float)$f - 32) * 5 / 9, 1);
}

/**
 * Convert mph to km/h.
 */
function mphToKmh(?string $mph): ?float
{
    if ($mph === null) return null;
    return round((float)$mph * 1.60934, 1);
}

/**
 * Convert inHg to hPa.
 */
function inHgToHpa(?string $inHg): ?float
{
    if ($inHg === null) return null;
    return round((float)$inHg * 33.8639, 1);
}

/**
 * Convert inches to mm.
 */
function inToMm(?string $in): ?float
{
    if ($in === null) return null;
    return round((float)$in * 25.4, 1);
}

/**
 * Convert miles to km.
 */
function miToKm(?string $mi): ?float
{
    if ($mi === null) return null;
    return round((float)$mi * 1.60934, 1);
}

/**
 * Fetch all sensor data from Ecowitt Open API.
 */
function fetchEcowitt(): ?array
{
    if (ECOWITT_APP_KEY === '' || ECOWITT_API_KEY === '' || ECOWITT_MAC === '') {
        return null;
    }

    $cached = cacheRead('ecowitt');
    if ($cached !== null) {
        return $cached;
    }

    $url = 'https://api.ecowitt.net/api/v3/device/real_time'
        . '?application_key=' . urlencode(ECOWITT_APP_KEY)
        . '&api_key=' . urlencode(ECOWITT_API_KEY)
        . '&mac=' . urlencode(ECOWITT_MAC)
        . '&call_back=all';

    $body = fetchUrl($url);
    if ($body === null) {
        return null;
    }

    $json = json_decode($body, true);
    if (!is_array($json) || ($json['code'] ?? -1) !== 0) {
        return null;
    }

    $d = $json['data'];

    $result = [
        'temp'           => fToC($d['outdoor']['temperature']['value'] ?? null),
        'wbgt'           => fToC($d['black_globe_temperature']['wbgt']['value'] ?? null),
        'humidity'       => isset($d['outdoor']['humidity']['value']) ? (int)$d['outdoor']['humidity']['value'] : null,
        'dewpt'          => fToC($d['outdoor']['dew_point']['value'] ?? null),
        'feelsLike'      => fToC($d['outdoor']['feels_like']['value'] ?? null),
        'windSpeed'      => mphToKmh($d['wind']['wind_speed']['value'] ?? null),
        'windGust'       => mphToKmh($d['wind']['wind_gust']['value'] ?? null),
        'winddir'        => isset($d['wind']['wind_direction']['value']) ? (int)$d['wind']['wind_direction']['value'] : null,
        'pressure'       => inHgToHpa($d['pressure']['relative']['value'] ?? null),
        'precipTotal'    => inToMm($d['rainfall_piezo']['daily']['value'] ?? $d['rainfall']['daily']['value'] ?? null),
        'solarRadiation' => isset($d['solar_and_uvi']['solar']['value']) ? (float)$d['solar_and_uvi']['solar']['value'] : null,
        'uv'             => isset($d['solar_and_uvi']['uvi']['value']) ? (int)$d['solar_and_uvi']['uvi']['value'] : null,
        'pm25'           => isset($d['pm25_ch1']['pm25']['value']) ? (float)$d['pm25_ch1']['pm25']['value'] : null,
        'pm25aqi'        => isset($d['pm25_ch1']['real_time_aqi']['value']) ? (float)$d['pm25_ch1']['real_time_aqi']['value'] : null,
        'lightningTime'  => isset($d['lightning']['distance']['time']) ? (int)$d['lightning']['distance']['time'] : null,
        'lightningCount' => isset($d['lightning']['count']['value']) ? (int)$d['lightning']['count']['value'] : null,
        'lightningDist'  => miToKm($d['lightning']['distance']['value'] ?? null),
        'obsTime'        => isset($d['outdoor']['temperature']['time']) ? (int)$d['outdoor']['temperature']['time'] : null,
    ];

    cacheWrite('ecowitt', $result);
    return $result;
}

// Build response
$data = fetchEcowitt();

/**
 * Validate that a URL uses https:// scheme only.
 */
function isValidServiceUrl(string $url): bool
{
    return (bool) filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 'https://') === 0;
}

$services = [];
if (defined('URL_WEATHERCLOUD') && URL_WEATHERCLOUD !== '' && isValidServiceUrl(URL_WEATHERCLOUD)) {
    $services[] = ['name' => 'Weathercloud', 'url' => URL_WEATHERCLOUD, 'icon' => '☁️', 'theme' => 'weathercloud'];
}
if (defined('URL_WUNDERGROUND') && URL_WUNDERGROUND !== '' && isValidServiceUrl(URL_WUNDERGROUND)) {
    $services[] = ['name' => 'Weather Underground', 'url' => URL_WUNDERGROUND, 'icon' => '🌍', 'theme' => 'wunderground'];
}
if (defined('URL_WINDY') && URL_WINDY !== '' && isValidServiceUrl(URL_WINDY)) {
    $services[] = ['name' => 'Windy', 'url' => URL_WINDY, 'icon' => '🌬️', 'theme' => 'windy'];
}
if (defined('URL_ECOWITT') && URL_ECOWITT !== '' && isValidServiceUrl(URL_ECOWITT)) {
    $services[] = ['name' => 'Ecowitt', 'url' => URL_ECOWITT, 'icon' => '📊', 'theme' => 'ecowitt'];
}

$response = [
    'ok'   => $data !== null,
    'data' => $data,
    'station' => [
        'name'     => defined('STATION_NAME') ? STATION_NAME : '',
        'location' => defined('STATION_LOCATION') ? STATION_LOCATION : '',
        'coords'   => defined('STATION_COORDS') ? STATION_COORDS : '',
        'region'   => defined('STATION_REGION') ? STATION_REGION : '',
    ],
    'services' => $services,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
