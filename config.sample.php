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
