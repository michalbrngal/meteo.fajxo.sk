<?php

declare(strict_types=1);

// Ecowitt Open API
// Get credentials at: https://www.ecowitt.net/user/index → API Key Management
define('ECOWITT_APP_KEY', 'APP_KEY');   // Your Application Key — get it from Ecowitt dashboard
define('ECOWITT_API_KEY', 'API_KEY');
define('ECOWITT_MAC', '00:11:22:33:44:55');

// Cache duration in seconds (avoid hammering external APIs)
define('CACHE_TTL', 60);
define('CACHE_DIR', __DIR__ . '/cache');

// Station info (displayed on the dashboard)
define('STATION_NAME', 'My Weather Station');
define('STATION_LOCATION', 'City, Country');
define('STATION_COORDS', '48°00\'00" N  17°00\'00" E · 200 m a.s.l.');
define('STATION_REGION', 'Region');

// Weather service URLs (leave empty to hide a service on the dashboard)
define('URL_WEATHERCLOUD', '');   // e.g. https://app.weathercloud.net/d1234567890
define('URL_WUNDERGROUND', '');   // e.g. https://www.wunderground.com/dashboard/pws/IXXXXXX
define('URL_WINDY', '');          // e.g. https://www.windy.com/station/pws-XXXXXXX
define('URL_ECOWITT', '');        // e.g. https://www.ecowitt.net/home/share?authorize=XXXXXX
