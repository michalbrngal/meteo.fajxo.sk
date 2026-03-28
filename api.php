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
 * Fetch current conditions from Weather Underground.
 */
function fetchWeatherUnderground(): ?array
{
    if (WU_API_KEY === '' || WU_STATION_ID === '') {
        return null;
    }

    $cached = cacheRead('wu');
    if ($cached !== null) {
        return $cached;
    }

    $url = 'https://api.weather.com/v2/pws/observations/current'
        . '?stationId=' . urlencode(WU_STATION_ID)
        . '&format=json&units=m'
        . '&apiKey=' . urlencode(WU_API_KEY);

    $body = fetchUrl($url);
    if ($body === null) {
        return null;
    }

    $json = json_decode($body, true);
    if (!is_array($json) || !isset($json['observations'][0])) {
        return null;
    }

    $obs = $json['observations'][0];
    $m = $obs['metric'] ?? [];

    $result = [
        'temp'           => $m['temp'] ?? null,
        'humidity'       => $obs['humidity'] ?? null,
        'dewpt'          => $m['dewpt'] ?? null,
        'windChill'      => $m['windChill'] ?? null,
        'windSpeed'      => $m['windSpeed'] ?? null,
        'windGust'       => $m['windGust'] ?? null,
        'winddir'        => $obs['winddir'] ?? null,
        'pressure'       => $m['pressure'] ?? null,
        'precipTotal'    => $m['precipTotal'] ?? null,
        'solarRadiation' => $obs['solarRadiation'] ?? null,
        'uv'             => $obs['uv'] ?? null,
        'obsTimeLocal'   => $obs['obsTimeLocal'] ?? null,
    ];

    cacheWrite('wu', $result);
    return $result;
}

/**
 * Fetch PM2.5 (and optionally other sensors) from Ecowitt Open API.
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
        . '&call_back=pm25_ch1';

    $body = fetchUrl($url);
    if ($body === null) {
        return null;
    }

    $json = json_decode($body, true);
    if (!is_array($json) || ($json['code'] ?? -1) !== 0) {
        return null;
    }

    $pm25 = $json['data']['pm25_ch1']['pm25']['value'] ?? null;
    $aqi = $json['data']['pm25_ch1']['real_time_aqi']['value'] ?? null;

    $result = [
        'pm25' => $pm25 !== null ? (float)$pm25 : null,
        'aqi'  => $aqi !== null ? (float)$aqi : null,
    ];

    cacheWrite('ecowitt', $result);
    return $result;
}

// Build combined response
$wu = fetchWeatherUnderground();
$ecowitt = fetchEcowitt();

$response = [
    'ok'       => $wu !== null,
    'weather'  => $wu,
    'ecowitt'  => $ecowitt,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
