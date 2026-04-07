# meteo.fajxo.sk

Personal weather station dashboard for **Meteostanica Sýnin** in Pezinok, Slovakia (48°18'06" N, 17°16'06" E, 220 m a.s.l.).

## Overview

The project consists of three components:

| Component | Description |
|-----------|-------------|
| **Web** | Single-page weather dashboard with a PHP API backend |
| **FOSHKplugin** | Dockerized data forwarder receiving HTTP pushes from the Ecowitt GW3000A gateway |
| **Zabbix** | Monitoring template for collecting weather metrics via HTTP JSON |

## Web

### Frontend — `index.html`

A static single-page dashboard that displays real-time weather conditions:

- Temperature, WBGT, feels-like, dew point
- Humidity, wind speed/gust/direction
- Atmospheric pressure
- Daily precipitation
- Solar radiation, UV index
- PM2.5 with AQI calculation

Data is fetched from `api.php` and rendered client-side. The page also links to external weather portals where live data is published:

- [Weathercloud](https://app.weathercloud.net/d4667886278#profile)
- [Weather Underground](https://www.wunderground.com/dashboard/pws/IPEZIN32)
- [Windy](https://www.windy.com/station/pws-0GzZW7bF)
- [Ecowitt](https://www.ecowitt.net/home/share?authorize=3JFD9G)

### Backend — `api.php`

A PHP API endpoint that:

1. Fetches sensor data from the [Ecowitt Open API](https://api.ecowitt.net/api/v3/device/real_time)
2. Converts imperial units to metric (°F→°C, mph→km/h, inHg→hPa, in→mm)
3. Caches responses to disk (configurable TTL, default 60 s)
4. Serves station metadata and weather service links from config
5. Returns a JSON response: `{ "ok": true, "data": { ... }, "station": { ... }, "services": [ ... ] }`

### Configuration

Copy the sample config and fill in your Ecowitt API credentials:

```bash
cp config.sample.php config.php
```

Edit `config.php`:

```php
// Ecowitt API credentials
define('ECOWITT_APP_KEY', 'your_application_key');
define('ECOWITT_API_KEY', 'your_api_key');
define('ECOWITT_MAC',     'AA:BB:CC:DD:EE:FF');
define('CACHE_TTL', 60);
define('CACHE_DIR', __DIR__ . '/cache');

// Station info (displayed on the dashboard)
define('STATION_NAME', 'My Weather Station');
define('STATION_LOCATION', 'City, Country');
define('STATION_COORDS', '48°00\'00" N  17°00\'00" E · 200 m a.s.l.');
define('STATION_REGION', 'Region');

// Weather service URLs (leave empty to hide from dashboard)
define('URL_WEATHERCLOUD', 'https://app.weathercloud.net/d1234567890');
define('URL_WUNDERGROUND', 'https://www.wunderground.com/dashboard/pws/IXXXXXX');
define('URL_WINDY',        'https://www.windy.com/station/pws-XXXXXXX');
define('URL_ECOWITT',      'https://www.ecowitt.net/home/share?authorize=XXXXXX');
```

The `cache/` directory must be writable by the web server.

## FOSHKplugin

[FOSHKplugin](https://github.com/hoetzgit/FOSHKplugin) is a middleware that intercepts HTTP data pushes from the Ecowitt GW3000A gateway and forwards them to multiple weather services and databases (e.g. Weather Underground, Weathercloud, Windy, InfluxDB, MQTT).

### Docker setup

The plugin runs as a Docker container built from a Python 3.11 Alpine image.

**Build & run:**

```bash
cd foshkplugin
docker compose up -d --build
```

**Key details:**

- Listens on port **8080** (host networking mode)
- Runs as non-root user (`1000:1000`)
- Configuration is bind-mounted from the host (`conf/foshkplugin.conf`)
- Logs are persisted to a host volume

### Volumes

| Container path | Purpose |
|---|---|
| `/opt/foshkplugin/conf` | Configuration directory (bind-mount from host) |
| `/opt/foshkplugin/logs` | Log files |

Edit `docker-compose.yml` to set the host paths for volumes:

```yaml
volumes:
  - /path/to/conf:/opt/foshkplugin/conf
  - /path/to/logs:/opt/foshkplugin/logs
```

### Configuration

Point the GW3000A's custom server to the host running FOSHKplugin on port 8080. Configure `foshkplugin.conf` to define forwarding targets and data transformations.

## Zabbix

A Zabbix 7.0 monitoring template (`zabbix/zbx_export_templates.yaml`) for polling weather data directly from the Ecowitt gateway via HTTP JSON.

### Template: `Template Ecowitt GW (HTTP JSON)`

**Monitored metrics:**

| Category | Items |
|----------|-------|
| **Outdoor** | Temperature, humidity, dew point |
| **Indoor** | Temperature, humidity (WH25) |
| **Wind** | Speed, gust, direction |
| **Pressure** | Absolute, relative (WH25) |
| **Rainfall** | Rate, daily, weekly, monthly, yearly, total (piezo + WH40H) |
| **Solar** | Solar radiation (W/m²), UV index |
| **Air quality** | PM2.5, PM2.5 24H avg, AQI (ch1) |
| **Lightning** | Strike count, battery |
| **Debug** | Heap, runtime, user interval |
| **Batteries** | WH40H rain sensor, PM2.5 sensor, lightning sensor |

### Import

1. Go to **Configuration → Templates → Import** in Zabbix
2. Upload `zabbix/zbx_export_templates.yaml`
3. Create a host and assign the template
4. Set the `{$ECOWITT.URL}` macro to the gateway's JSON endpoint (e.g. `http://<gateway-ip>/get_livedata_info`)

The template uses a single HTTP agent item (`ecowitt.raw`) as the master item, with all other items as dependent items using JSONPath and regex preprocessing.

## Project structure

```
├── index.html              # Weather dashboard (frontend)
├── api.php                 # Ecowitt API proxy (backend)
├── config.php              # API credentials (not in repo)
├── config.sample.php       # Sample configuration
├── cache/                  # Disk cache for API responses
├── foshkplugin/
│   ├── docker-compose.yml  # Docker Compose for FOSHKplugin
│   ├── Dockerfile          # Python 3.11 Alpine image
│   └── foshkplugin.py      # FOSHKplugin source
└── zabbix/
    └── zbx_export_templates.yaml  # Zabbix monitoring template
```

## License

This project is licensed under [CC BY-NC 4.0](https://creativecommons.org/licenses/by-nc/4.0/). You are free to share and adapt it for non-commercial purposes with attribution.

The `foshkplugin/` directory contains third-party code ([FOSHKplugin](https://github.com/hoetzgit/FOSHKplugin)) which may be subject to its own license terms.
