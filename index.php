<?php
/**
 * Cloudflare Cache & Development Control Panel
 * 
 * Secure web interface to interact with cf-helper.py.
 * Requires a CF_ACCESS_KEY defined in .secrets or .env.local to access.
 */
session_start();

// Helper to parse key-value files (.secrets, .env.local, .env)
function loadEnvSecrets($filepath) {
    $env = [];
    if (file_exists($filepath)) {
        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            // Strip inline comments
            if (strpos($line, '#') !== false) {
                list($line) = explode('#', $line, 2);
                $line = trim($line);
            }
            if (strpos($line, '=') !== false) {
                list($key, $val) = explode('=', $line, 2);
                $env[trim($key)] = trim(trim($val), "\"'");
            }
        }
    }
    return $env;
}

// 1. Load Configurations
$secrets = loadEnvSecrets(__DIR__ . '/.secrets');
$envLocal = loadEnvSecrets(__DIR__ . '/.env.local');
$envBase = loadEnvSecrets(__DIR__ . '/.env');

// Retrieve trigger key
$triggerKey = $secrets['CF_ACCESS_KEY'] 
           ?? $envLocal['CF_ACCESS_KEY'] 
           ?? $envBase['CF_ACCESS_KEY'] 
           ?? '';

// Retrieve project name
$projectName = $secrets['PROJECT_NAME'] 
            ?? $envLocal['PROJECT_NAME'] 
            ?? $envBase['PROJECT_NAME'] 
            ?? 'CF Cache Manager';

$isConfigured = !empty($triggerKey);

// 2. Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

$loginError = '';

// 3. Handle Login (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['key'])) {
    if ($isConfigured && hash_equals($triggerKey, $_POST['key'])) {
        $_SESSION['cf_auth'] = true;
        // Redirect to prevent form resubmission on refresh
        header("Location: ?action=status");
        exit;
    } else {
        $loginError = "Invalid Access Key.";
    }
}

// 4. Verify Authentication Session
$isAuthenticated = isset($_SESSION['cf_auth']) && $_SESSION['cf_auth'] === true;

$action = $_GET['action'] ?? 'status';
$allowedActions = ['status', 'on', 'off', 'purge'];
if (!in_array($action, $allowedActions)) {
    $action = 'status';
}

// Convert ANSI escape codes to HTML styled spans for terminal rendering
function ansiToHtml($text) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = str_replace(
        ["\033[92m", "\033[93m", "\033[91m", "\033[94m", "\033[96m", "\033[1m", "\033[0m"],
        [
            '<span style="color:#10b981;font-weight:bold;">', // Green
            '<span style="color:#f59e0b;font-weight:bold;">', // Yellow
            '<span style="color:#ef4444;font-weight:bold;">', // Red
            '<span style="color:#3b82f6;font-weight:bold;">', // Blue
            '<span style="color:#06b6d4;font-weight:bold;">', // Cyan
            '<span style="font-weight:bold;">',
            '</span>'
        ],
        $text
    );
    return $text;
}

// 5. Execute Script if Authenticated
$terminalOutput = '';
if ($isAuthenticated) {
    $scriptPath = __DIR__ . '/cf-helper.py';
    if (!file_exists($scriptPath)) {
        $terminalOutput = "Error: cf-helper.py script not found at $scriptPath";
    } elseif (!is_executable($scriptPath)) {
        $terminalOutput = "Error: cf-helper.py is not executable. Please run 'chmod +x cf-helper.py'.";
    } else {
        $cmd = escapeshellcmd($scriptPath) . ' ' . escapeshellarg($action);
        $terminalOutput = shell_exec($cmd);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($projectName) ?> | CDN Cache Control</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #0f172a;
            --card-dark: rgba(30, 41, 59, 0.7);
            --border-dark: rgba(255, 255, 255, 0.08);
            --primary: #f97316;
            --primary-hover: #ea580c;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --green: #10b981;
            --red: #ef4444;
            --blue: #3b82f6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
            background-image: radial-gradient(circle at 10% 20%, rgba(249, 115, 22, 0.05) 0%, transparent 40%),
                              radial-gradient(circle at 90% 80%, rgba(59, 130, 246, 0.05) 0%, transparent 40%);
        }

        .dashboard-container {
            width: 100%;
            max-width: 750px;
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-title {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .logo-title span {
            color: var(--primary);
        }

        .logo-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Access Card Form Styling */
        .auth-card {
            text-align: center;
            padding: 20px 0;
        }

        .auth-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .auth-title {
            font-size: 1.4rem;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .auth-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 24px;
            line-height: 1.5;
        }

        .form-group {
            display: flex;
            gap: 12px;
            max-width: 480px;
            margin: 0 auto;
        }

        .form-input {
            flex: 1;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-dark);
            border-radius: 12px;
            padding: 14px 18px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--text-main);
        }

        .btn-primary {
            background: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        /* Control Panel Grid Styling */
        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 14px;
            margin-bottom: 30px;
        }

        .control-btn {
            flex-direction: column;
            padding: 18px 12px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid var(--border-dark);
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: all 0.2s ease;
        }

        .control-btn svg {
            width: 24px;
            height: 24px;
            margin-bottom: 8px;
            stroke-width: 2;
            transition: transform 0.2s ease;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.15);
            color: var(--text-main);
            transform: translateY(-2px);
        }

        .control-btn:hover svg {
            transform: scale(1.1);
        }

        .control-btn.active {
            background: rgba(249, 115, 22, 0.1);
            border-color: var(--primary);
            color: var(--text-main);
        }

        .control-btn.active.status-type { color: var(--blue); border-color: var(--blue); background: rgba(59, 130, 246, 0.08); }
        .control-btn.active.purge-type { color: var(--primary); border-color: var(--primary); background: rgba(249, 115, 22, 0.08); }
        .control-btn.active.on-type { color: var(--green); border-color: var(--green); background: rgba(16, 185, 129, 0.08); }
        .control-btn.active.off-type { color: var(--red); border-color: var(--red); background: rgba(239, 68, 68, 0.08); }

        /* Terminal Window Styling */
        .terminal-window {
            background: #090d16;
            border: 1px solid var(--border-dark);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: inset 0 4px 12px rgba(0,0,0,0.5);
        }

        .terminal-header {
            background: #111827;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .terminal-dots {
            display: flex;
            gap: 6px;
        }

        .terminal-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .terminal-dot.close { background: #ff5f56; }
        .terminal-dot.min { background: #ffbd2e; }
        .terminal-dot.max { background: #27c93f; }

        .terminal-title {
            font-family: 'Space Grotesk', monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .terminal-body {
            padding: 24px;
            font-family: 'Space Grotesk', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            white-space: pre-wrap;
            color: #e2e8f0;
            min-height: 150px;
            max-height: 380px;
            overflow-y: auto;
        }

        /* Config Warnings styling */
        .warning-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--red);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .warning-title {
            font-weight: 600;
            color: var(--red);
            margin-bottom: 4px;
        }

        .warning-banner code {
            background: rgba(0,0,0,0.3);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="logo-section">
            <h1 class="logo-title"><?= htmlspecialchars($projectName) ?></h1>
            <div class="logo-sub">Cloudflare Edge CDN Control</div>
        </div>

        <?php if (!$isConfigured): ?>
            <!-- Setup Warning Screen -->
            <div class="warning-banner">
                <div class="warning-title">⚠️ Action Required: Access Key Missing</div>
                To access this control panel, you must configure a secure access key. Add the <code>CF_ACCESS_KEY</code> variable to your <code>.secrets</code> file:
                <div style="margin-top: 10px; font-family: monospace; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 6px;">
                    CF_ACCESS_KEY=your_secure_access_key
                </div>
            </div>
            <div class="auth-card" style="margin-top: 20px;">
                <p class="auth-desc">Once configured, reload this page to access the CDN controls.</p>
            </div>

        <?php elseif (!$isAuthenticated): ?>
            <!-- Authentication Gate Screen -->
            <div class="auth-card">
                <div class="auth-icon">🔒</div>
                <h2 class="auth-title">Access Verification</h2>
                <p class="auth-desc">This control panel is private. Enter your Access Key to access CDN controls.</p>
                
                <form method="POST" class="form-group">
                    <input type="password" name="key" placeholder="Enter Access Key" required autocomplete="off" class="form-input">
                    <button type="submit" class="btn btn-primary">Verify Access</button>
                </form>
                <?php if ($loginError): ?>
                    <p style="color:var(--red); margin-top:16px; font-size:0.9rem; font-weight:600;"><?= htmlspecialchars($loginError) ?></p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Authenticated Action Dashboard -->
            <div class="controls-grid">
                <a href="?action=status" class="btn control-btn status-type <?= $action === 'status' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z"></path></svg>
                    Check Status
                </a>
                <a href="?action=purge" class="btn control-btn purge-type <?= $action === 'purge' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Purge CDN Cache
                </a>
                <a href="?action=on" class="btn control-btn on-type <?= $action === 'on' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Dev Mode ON
                </a>
                <a href="?action=off" class="btn control-btn off-type <?= $action === 'off' ? 'active' : '' ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                    Dev Mode OFF
                </a>
            </div>

            <!-- Terminal Output Block -->
            <div class="terminal-window">
                <div class="terminal-header">
                    <div class="terminal-dots">
                        <div class="terminal-dot close"></div>
                        <div class="terminal-dot min"></div>
                        <div class="terminal-dot max"></div>
                    </div>
                    <div class="terminal-title">cf-helper.py CLI Execution — <?= htmlspecialchars($action) ?></div>
                    <div style="width:42px;"></div>
                </div>
                <div class="terminal-body"><?= ansiToHtml(trim($terminalOutput)) ?></div>
            </div>
            
            <div style="text-align: center; margin-top: 24px;">
                <p style="font-size:0.85rem; color:var(--text-muted);">
                    Authorized Session &bull; <a href="?action=logout" style="color:var(--primary); text-decoration:none; font-weight:600; transition:color 0.2s;" onmouseover="this.style.color='var(--primary-hover)'" onmouseout="this.style.color='var(--primary)'">Lock Panel</a>
                </p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
