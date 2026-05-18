# Cloudflare Edge Cache Manager

A standalone, secure, and beautiful toolset to manage Cloudflare CDN Cache and Development Mode during web development. 

This repository contains two primary components:
1. `cf-helper.py` - A robust, dependency-free Python CLI script for terminal usage.
2. `index.php` - A gorgeous, secure PHP Web Control Panel with session-based authentication.

## Installation

Create a local configuration file named `.secrets` in the root of this folder:

```ini
CLOUDFLARE_API_TOKEN=your_api_token_here
CLOUDFLARE_ZONE_ID=your_zone_id_here

# Secret security access key to authorize control panel sessions
CF_ACCESS_KEY=your_secret_access_key_here

# (Optional) Customize the header title of your web dashboard
PROJECT_NAME=My Project Name
```

## Usage

### Web Interface
Navigate to the `cf-manager` directory in your browser (e.g., `http://your-domain.com/cf-manager/`). You will be prompted to enter the `CF_ACCESS_KEY` to authenticate securely.

### CLI Interface
Run the python script directly from the terminal:
- `./cf-helper.py status`
- `./cf-helper.py on`
- `./cf-helper.py off`
- `./cf-helper.py purge`
