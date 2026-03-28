<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

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
        'obsTime'        => isset($d['outdoor']['temperature']['time']) ? (int)$d['outdoor']['temperature']['time'] : null,
    ];

    cacheWrite('ecowitt', $result);
    return $result;
}

// Build response
$data = fetchEcowitt();

$response = [
    'ok'   => $data !== null,
    'data' => $data,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
