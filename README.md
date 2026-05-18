# Cloudflare Edge Cache Manager

A standalone, secure, and beautiful toolset to manage Cloudflare CDN Cache and Development Mode during web development. 

This repository contains two primary components:
1. `cf-helper.py` - A robust, dependency-free Python CLI script for terminal usage.
2. `cf-cache.php` - A gorgeous, secure PHP Web Control Panel with session-based authentication.

## Installation

Create a local configuration file named `.secrets` in the root of this folder:

```ini
CLOUDFLARE_API_TOKEN=your_api_token_here
CLOUDFLARE_ZONE_ID=your_zone_id_here

# Secret security bypass key to access the cf-cache.php web control panel
CLOUDFLARE_TRIGGER_KEY=your_secret_trigger_key_here
```

## Usage

### Web Interface
Navigate to the `cf-cache.php` file in your browser. You will be prompted to enter the `CLOUDFLARE_TRIGGER_KEY` to authenticate securely.

### CLI Interface
Run the python script directly from the terminal:
- `./cf-helper.py status`
- `./cf-helper.py on`
- `./cf-helper.py off`
- `./cf-helper.py purge`
