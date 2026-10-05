# 🐘 MTI Knowledge Assistant - LAMP Stack (PHP + MySQL) Backend Deployment Guide

This directory contains the **pure PHP + MySQL backend** implementation of the MTI Knowledge Assistant. It is designed for deployment on any standard **LAMP (Linux, Apache, MySQL, PHP)** server without requiring Python, Docker, or extra background processes.

---

## 🏗️ Architecture & Component Overview

```
                      [ Client Browser (React SPA) ]
                                    │
                                    ▼
                      [ Apache Web Server (Port 80/443) ]
                                    │
           ┌────────────────────────┴────────────────────────┐
           │                                                 │
  (Serves Static Files)                            (Routes API Requests)
           │                                                 │
           ▼                                                 ▼
[ React Built Frontend ]                           [ PHP API Front Controller ]
   (index.html, JS, CSS)                                (`index.php`)
                                                             │
                                        ┌────────────────────┴────────────────────┐
                                        │                                         │
                                        ▼                                         ▼
                            [ MySQL / MariaDB Database ]                [ LLM REST API Service ]
                             (`mti_assistant` tables)                   (Groq & Gemini APIs)
```

---

## 🛠️ Requirements & Prerequisites

1. **Web Server**: Apache HTTP Server with `mod_rewrite` enabled.
2. **PHP**: PHP 7.4 or 8.x with `pdo_mysql`, `curl`, and `json` extensions.
3. **Database**: MySQL 5.7+ or MariaDB 10.3+.
4. **LLM API Key**: At least one valid API Key:
   - **Groq API Key**: `GROQ_API_KEY` (Recommended for fast `llama-3.3-70b-versatile` answers).
   - **Google Gemini API Key**: `GEMINI_API_KEY` (Fallback or primary `gemini-1.5-flash`).

---

## 🚀 Step-by-Step LAMP Deployment Instructions

### Step 1: Copy PHP Backend Files
Copy the contents of `php_backend/` to your server's web root directory (e.g. `/var/www/html/api/` or `/public_html/api/`).

```bash
cp -r php_backend/* /var/www/html/api/
```

### Step 2: Configure Environment / `config.php`
Edit `php_backend/config.php` (or set environment variables in Apache/system):

```php
// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mti_assistant');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// LLM API Credentials
define('PRIMARY_LLM_PROVIDER', 'groq'); // 'groq' or 'gemini'
define('GROQ_API_KEY', 'your_groq_api_key_here');
define('GEMINI_API_KEY', 'your_gemini_api_key_here');
```

### Step 3: Initialize Database Schema
You can either let `db.php` automatically create tables on your first request, OR run the SQL script in phpMyAdmin / MySQL CLI:

```bash
mysql -u root -p < /var/www/html/api/schema.sql
```

### Step 4: Ingest PDF Literature into MySQL (RAG Search)
Place your meteorological PDF training manuals in `php_backend/data/` and run the ingestion script:

```bash
cd /var/www/html/api
php ingest_pdf.php data/your_manual.pdf
```

This splits the manual into text chunks and indexes them into the `document_chunks` table for quick keyword search.

### Step 5: Build & Connect React Frontend
1. Open `frontend/MTI_RAG_Project_Version 2/.env` and point the API base URL to your Apache PHP backend URL:
   ```env
   VITE_API_BASE_URL=http://your-server-ip/api
   ```
2. Build the production React app:
   ```bash
   cd "frontend/MTI_RAG_Project_Version 2"
   npm run build
   ```
3. Copy the generated files inside `dist/` to your Apache document root (`/var/www/html/`).

---

## 📡 API Endpoints Provided by `index.php`

| Endpoint | Method | Description |
| :--- | :--- | :--- |
| `/health` | `GET` | Health check endpoint returning `{"status": "ok"}` |
| `/auth/login` | `POST` | User login, returns JWT token & user profile |
| `/auth/anonymous` | `POST` | Guest login, returns bearer token |
| `/user/profile` | `GET` / `PUT` | Fetch or update user preferences & instructions |
| `/chat` | `POST` | Main RAG chat endpoint (Domain guardrails, chunk retrieval, LLM call) |
| `/chat/translate` | `POST` | Translates responses to Hindi/other languages via LLM |
| `/documents` | `GET` | Lists indexed training documents |
| `/documents/upload` | `POST` | Uploads a new PDF document |
| `/documents/{filename}` | `DELETE` | Deletes a document & removes DB chunks |

---

## 🔒 Security & Performance Tips

1. Ensure write permissions on `data/` folder for PDF uploads:
   ```bash
   chmod -R 775 /var/www/html/api/data
   chown -R www-data:www-data /var/www/html/api/data
   ```
2. Make sure `mod_rewrite` is enabled in Apache:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```
