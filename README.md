# ⚡ Cloudflare Edge Cache Manager

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Security: Hardened](https://img.shields.io/badge/Security-Hardened-brightgreen.svg)]()
[![Stack: PHP_Python](https://img.shields.io/badge/Stack-PHP%20%2F%20Python-blue.svg)]()

A lightweight, secure, and beautiful standalone toolset (CLI & Web Dashboard) designed to bypass or purge Cloudflare Edge Caches instantly during active web development. 

This repository contains two unified, generic components:
1. **`cf-helper.py`** – A dependency-free, lightweight Python CLI tool to run rapid terminal commands.
2. **`index.php`** – A stunning, glassmorphism-style PHP Web Control Panel with session-fallback secure cookie authorization, designed to reside safely in any subdirectory or function as a standalone manager.

---

## 🚀 Key Features

* **Instant Edge Purging:** Clean your entire Cloudflare edge cache in a single click or command.
* **Development Mode Toggle:** Turn off edge caching temporarily (3-hour windows) so you see your edits in real-time, without turning off security features or DNS proxies.
* **Zero Dependencies:** Python CLI uses only standard library (`urllib`), meaning zero `pip install` issues.
* **Hardened Security:** Built-in `.htaccess` rules block public access to `.secrets` and configurations, and uses a secure HTTP-Only cookie signature backup in case standard PHP session files fail on your host.
* **Tailored Aesthetics:** A gorgeous modern UI featuring premium dark mode gradients and micro-animations.

---

## 🛠️ Step-by-Step Installation

### 1. Position the Files
Clone this repository or add it as a submodule into your project directory:
```bash
git submodule add https://github.com/DrShivang/CF-Manager.git cf-manager
```

### 2. Configure Credentials
Create a local configuration file named `.secrets` in the root of the `cf-manager/` folder (this file is already safely listed in `.gitignore`):

```ini
CLOUDFLARE_API_TOKEN=your_cloudflare_api_token_here
CLOUDFLARE_ZONE_ID=your_cloudflare_zone_id_here

# Secret security access key to authorize web control panel sessions
CF_ACCESS_KEY=your_secret_access_key_here

# (Optional) Customize the header title of your web dashboard
PROJECT_NAME=My Project Name
```

> [!TIP]
> **How to get your credentials:**
> 1. **Zone ID:** Go to your Cloudflare Dashboard -> select your domain. On the **Overview** tab, scroll down to the bottom right sidebar to find the **Zone ID**.
> 2. **API Token:** Go to **My Profile** (top-right avatar) -> **API Tokens** -> **Create Token**. Use a custom template with:
>    * `Zone` -> `Cache Purge` -> `Edit` (required to clear cache)
>    * `Zone` -> `Zone Settings` -> `Edit` (required to toggle Development Mode)
>    * `Zone` -> `Zone Settings` -> `Read` (required to read current status)
>    * Under **Zone Resources**, select `Include` -> `Specific zone` -> select your domain.

---

## 💡 Handy Active Development Use Cases

### 1. Bypassing Cache During Active Front-End Editing 🎨
When you are making rapid styling updates (CSS/JS modifications), browser and Cloudflare edge caching will often hide changes, forcing you to constantly rename assets or clear your browser cache.
* **Standard Fix (Slow):** Manually logging into Cloudflare Dashboard, clicking through multiple menus to turn on Dev Mode.
* **CF-Manager Fix (Instant):** 
  * Open your terminal and run:
    ```bash
    ./cf-helper.py on
    ```
  * Or navigate to your `/cf-manager/` dashboard, enter your **Access Key**, and click **"Enable Development Mode"**.
  * **Result:** Cloudflare suspends edge caching for **3 hours** (or until manually disabled via `./cf-helper.py off`), allowing you to hit refresh in your browser and see every code change instantly!

### 2. Pushing Hotfixes & Production Deployments 🚀
When launching new features, updating pages, or replacing image assets, existing users will continue to load stale, cached assets from the edge CDN.
* **Standard Fix:** Waiting up to 24+ hours for caches to expire.
* **CF-Manager Fix:** Immediately upon completing a code deployment, clear the cache.
  * Run the single command in your terminal:
    ```bash
    ./cf-helper.py purge
    ```
  * Or click **"Purge Edge Cache"** in the Web Dashboard.
  * **Result:** All edge servers are wiped immediately, and the very next visitor receives the fresh, newly deployed files.

### 3. Automated Git Hooks (Zero-Click Purging) 🤖
To ensure you never forget to purge the edge cache after pushing an update to production, you can tie `cf-helper.py` directly into a local Git post-merge or pre-push hook!
* **Setup Hook:** Create a file named `.git/hooks/post-merge` inside your parent repository:
  ```bash
  #!/bin/bash
  # Automatically purge cache after pulling updates on the production server
  echo "Wiping Cloudflare CDN Edge Cache..."
  python3 cf-manager/cf-helper.py purge
  ```
* **Result:** Every time you run `git pull` on your production server, the local cache will be instantly wiped without manual intervention.

---

## 🔒 Enterprise-Grade Security

CF-Manager is engineered to run securely in production environments:
* **Apache Protection (`.htaccess`):** The built-in `.htaccess` uses highly compatible fallback structures to prevent direct public HTTP access to `.secrets`, `.secrets.example`, `README.md`, and `.git` folders.
* **Cryptographic Cookie Backup:** Web sessions are secured with an Access Key. If your hosting provider's default PHP session store is misconfigured or fails to write files, the dashboard automatically falls back to an HTTP-Only cookie signed with a SHA-256 hash of your `CF_ACCESS_KEY` and your specific browser footprint (`HTTP_USER_AGENT`), guaranteeing seamless access.
* **Origin Protection:** No-Cache HTTP headers prevent Cloudflare itself from caching the administrator dashboard, completely resolving infinite login loops.
