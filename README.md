
![Dashboard](https://github.com/snick512/shortURL/blob/master/dash.png?raw=true)


# ShortURL — Simple PHP URL Shortener & Analytics

A lightweight, self-hosted URL shortener with click tracking, analytics dashboard, and API.  
Stores data in SQLite and JSON files.  
**No dependencies** beyond PHP and SQLite.

---

## Features

- **Shorten URLs** via API
- **Custom or auto-generated short names**
- **Tracks visits** (IP, country, city, referrer, etc.)
- **Analytics dashboard** (`dashboard.php`):  
  - Total clicks, top links, top countries, time series, world map, recent visits
- **Terminal dashboard** (`dashboard.sh`)
- **API for adding links** (`url.php`)
- **Clipboard integration** (`urlgen.sh`)
- **Geolocation** via [ip-api.com](http://ip-api.com/)
- **No external DB required** (uses SQLite)

---

## File Overview

| File            | Purpose                                 |
|-----------------|-----------------------------------------|
| `index.php`     | Main redirector & visit logger          |
| `url.php`       | API for creating short links            |
| `api.php`       | Analytics API (JSON)                    |
| `dashboard.php` | Web analytics dashboard                 |
| `dashboard.sh`  | Terminal analytics dashboard            |
| `urlgen.sh`     | Bash script to create short links       |
| `.htaccess`     | Apache rewrite rules                    |
| `urls.json`     | Stores short link mappings (auto-gen)   |
| `visits.sqlite` | SQLite DB for analytics (auto-gen)      |

---

## Usage

### 1. Requirements

- PHP 7.2+ with SQLite enabled
- Apache (or compatible web server with `.htaccess` support)
- `jq` and `xclip` for shell scripts (optional)

### 2. Setup

1. **Clone or copy files to your server.**
2. **Edit config values** in `url.php`, `index.php`, `dashboard.php`, and scripts:
   - Set your API key(s), base URL, and dashboard password.
3. **Ensure PHP can write to the directory** (for `urls.json` and `visits.sqlite`).
4. **Configure your web server** to use `.htaccess` for pretty URLs.

### 3. Creating Short Links

- **Via API:**  
  `GET /url.php?key=YOUR_API_KEY&url=https://example.com[&short=customname]`
- **Via Bash script:**  
  ```sh
  ./urlgen.sh "https://example.com" [customname]


**Support on [Liberapay](https://go.tyclifford.com/liberapay)**
