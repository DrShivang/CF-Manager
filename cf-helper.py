#!/usr/bin/env python3
import os
import sys
import json
import urllib.request
import urllib.error

# Color Constants for Terminal Output
GREEN = "\033[92m"
YELLOW = "\033[93m"
RED = "\033[91m"
BLUE = "\033[94m"
CYAN = "\033[96m"
BOLD = "\033[1m"
RESET = "\033[0m"

ENV_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")
ENV_LOCAL_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env.local")
ENV_SECRETS_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".secrets")

def load_env_file(filepath):
    env = {}
    if os.path.exists(filepath):
        with open(filepath, "r") as f:
            for line in f:
                # Strip inline comments
                if "#" in line:
                    line = line.split("#", 1)[0]
                line = line.strip()
                if not line:
                    continue
                if "=" in line:
                    key, val = line.split("=", 1)
                    env[key.strip()] = val.strip().strip('"').strip("'")
    return env

def get_config():
    # Load .secrets or .env.local first (gitignored local secrets), fallback to .env
    env_secrets = load_env_file(ENV_SECRETS_FILE)
    env_local = load_env_file(ENV_LOCAL_FILE)
    env_base = load_env_file(ENV_FILE)
    
    token = os.environ.get("CLOUDFLARE_API_TOKEN") or env_secrets.get("CLOUDFLARE_API_TOKEN") or env_local.get("CLOUDFLARE_API_TOKEN") or env_base.get("CLOUDFLARE_API_TOKEN")
    zone_id = os.environ.get("CLOUDFLARE_ZONE_ID") or env_secrets.get("CLOUDFLARE_ZONE_ID") or env_local.get("CLOUDFLARE_ZONE_ID") or env_base.get("CLOUDFLARE_ZONE_ID")
    return token, zone_id

def make_request(url, method="GET", data=None, token=None):
    if not token:
        print(f"{RED}{BOLD}Error:{RESET} Cloudflare API Token is missing.")
        sys.exit(1)
        
    req = urllib.request.Request(url, method=method)
    req.add_header("Authorization", f"Bearer {token}")
    req.add_header("Content-Type", "application/json")
    
    if data is not None:
        jsondata = json.dumps(data).encode("utf-8")
        req.data = jsondata
        
    try:
        # Add 10-second network timeout to prevent indefinite hangs
        with urllib.request.urlopen(req, timeout=10) as response:
            res_body = response.read().decode("utf-8")
            return json.loads(res_body)
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        try:
            err_json = json.loads(err_body)
            messages = ", ".join([err.get("message", "") for err in err_json.get("errors", [])])
            print(f"{RED}{BOLD}API Error ({e.code}):{RESET} {messages or err_body}")
        except Exception:
            print(f"{RED}{BOLD}HTTP Error ({e.code}):{RESET} {e.reason}")
        sys.exit(1)
    except Exception as e:
        print(f"{RED}{BOLD}Connection Error:{RESET} {str(e)}")
        sys.exit(1)

def show_status(token, zone_id):
    url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/settings/development_mode"
    res = make_request(url, "GET", token=token)
    
    result = res.get("result", {})
    val = result.get("value", "unknown")
    time_remaining = result.get("time_remaining", 0)
    
    print(f"\n{BOLD}Cloudflare Development Mode Status:{RESET}")
    print("-" * 45)
    if val == "on":
        mins, secs = divmod(time_remaining, 60)
        hours, mins = divmod(mins, 60)
        print(f"Status:         {GREEN}{BOLD}ENABLED (ON){RESET}")
        print(f"Time Remaining: {YELLOW}{hours}h {mins}m {secs}s{RESET}")
    else:
        print(f"Status:         {RED}{BOLD}DISABLED (OFF){RESET}")
    print("-" * 45 + "\n")

def toggle_dev_mode(token, zone_id, value):
    url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/settings/development_mode"
    data = {"value": value}
    res = make_request(url, "PATCH", data=data, token=token)
    
    if res.get("success"):
        status_str = f"{GREEN}ON{RESET}" if value == "on" else f"{RED}OFF{RESET}"
        print(f"{GREEN}{BOLD}Success:{RESET} Development mode successfully turned {status_str}!")
        show_status(token, zone_id)
    else:
        print(f"{RED}{BOLD}Failed to set Development Mode.{RESET}")

def purge_cache(token, zone_id):
    url = f"https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache"
    data = {"purge_everything": True}
    res = make_request(url, "POST", data=data, token=token)
    
    if res.get("success"):
        print(f"{GREEN}{BOLD}Success:{RESET} Cloudflare edge cache has been fully purged!")
    else:
        print(f"{RED}{BOLD}Failed to purge cache.{RESET}")

def print_help():
    print(f"""{BOLD}Cloudflare Development CLI Helper{RESET}

{BOLD}Usage:{RESET}
  python3 cf-helper.py <command>

{BOLD}Commands:{RESET}
  {CYAN}status{RESET}    Show current Development Mode status
  {CYAN}on{RESET}        Turn Cloudflare Development Mode ON (expires in 3 hours)
  {CYAN}off{RESET}       Turn Cloudflare Development Mode OFF
  {CYAN}purge{RESET}     Purge all edge cache immediately
  {CYAN}help{RESET}      Show this help message

{BOLD}Configuration:{RESET}
  Add the following to your local config file {BOLD}.secrets{RESET} or {BOLD}.env.local{RESET} (recommended & gitignored):
    CLOUDFLARE_API_TOKEN=your-api-token
    CLOUDFLARE_ZONE_ID=your-zone-id
  
  Or run with environment variables:
    CLOUDFLARE_API_TOKEN=xxx CLOUDFLARE_ZONE_ID=yyy python3 cf-helper.py <command>
""")

def main():
    if len(sys.argv) < 2:
        print_help()
        sys.exit(1)
        
    cmd = sys.argv[1].lower()
    
    if cmd in ("help", "--help", "-h"):
        print_help()
        sys.exit(0)
        
    token, zone_id = get_config()
    
    if not token or not zone_id:
        print(f"{YELLOW}{BOLD}Missing Configuration!{RESET}")
        print("Please configure Cloudflare API details.")
        print(f"We recommend creating a local config file {BOLD}.secrets{RESET} or {BOLD}.env.local{RESET} in this directory:")
        print("  CLOUDFLARE_API_TOKEN=your-token")
        print("  CLOUDFLARE_ZONE_ID=your-zone-id\n")
        print("Or pass them on the command line:")
        print("  CLOUDFLARE_API_TOKEN=xxx CLOUDFLARE_ZONE_ID=yyy python3 cf-helper.py status\n")
        sys.exit(1)
        
    if cmd == "status":
        show_status(token, zone_id)
    elif cmd == "on":
        toggle_dev_mode(token, zone_id, "on")
    elif cmd == "off":
        toggle_dev_mode(token, zone_id, "off")
    elif cmd == "purge":
        purge_cache(token, zone_id)
    else:
        print(f"{RED}Unknown command: {cmd}{RESET}")
        print_help()
        sys.exit(1)

if __name__ == "__main__":
    main()
