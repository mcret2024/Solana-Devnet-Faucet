<?php
header("Content-Type: application/json; charset=utf-8");

// =========================
// CONFIG
// =========================

// ✔ IP 제한 / 쿨다운 기능 사용 여부 (true = ON / false = OFF)
$ENABLE_COOLDOWN = false;

// 쿨다운 시간(초) – (ON일 때만 적용됨)
$COOLDOWN_SECONDS = 3600; // 1시간

$RPC_URL        = "https://api.devnet.solana.com";
$ADMIN_WALLET   = "Fve3Yqxyuj5GTLK4YaECADPdzcrRVovtsVFnDuY7n35g";
$TOKEN_MINT     = "DUbbqANBKJqAUCJveSEFgVPGHDwkdc6d9UiQyxBLcyN3";
$AIRDROP_SOL    = 0.1;
$TOKEN_DECIMALS = 6;

$LOG_FILE      = __DIR__ . "/airdrop_log.txt";
$COOLDOWN_FILE = __DIR__ . "/airdrop_limit.json";

// =========================
// Helper: Log
// =========================
function log_airdrop($stage, $data = null) {
    global $LOG_FILE;
    $line = "[" . date("Y-m-d H:i:s") . "] [$stage] ";
    if ($data !== null) $line .= json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($LOG_FILE, $line . "\n", FILE_APPEND);
}

// =========================
// Helper: Get Client IP
// =========================
function get_client_ip() {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ipList = explode(',', $_SERVER[$k]);
            return trim($ipList[0]);
        }
    }
    return 'unknown';
}

// =========================
// Cooldown DB (JSON)
// =========================
function load_cooldowns() {
    global $COOLDOWN_FILE;
    if (!file_exists($COOLDOWN_FILE)) return [];
    return json_decode(file_get_contents($COOLDOWN_FILE), true) ?: [];
}
function save_cooldowns($map) {
    global $COOLDOWN_FILE;
    file_put_contents($COOLDOWN_FILE, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

// =========================
// Solana RPC
// =========================
function solana_rpc($method, $params = []) {
    global $RPC_URL;

    $payload = json_encode([
        "jsonrpc" => "2.0",
        "id"      => 1,
        "method"  => $method,
        "params"  => $params
    ]);

    $ch = curl_init($RPC_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $res = curl_exec($ch);
    curl_close($ch);

    return json_decode($res, true);
}

// =========================
// 관리자 잔액 조회
// =========================
if ($_SERVER["REQUEST_METHOD"] === "GET" && ($_GET["mode"] ?? "") === "admin") {

    $solRes = solana_rpc("getBalance", [$ADMIN_WALLET]);
    $sol = ($solRes["result"]["value"] ?? 0) / 1e9;

    $tokenRes = solana_rpc("getTokenAccountsByOwner", [
        $ADMIN_WALLET,
        ["mint" => $TOKEN_MINT],
        ["encoding" => "jsonParsed"]
    ]);

    $token = 0;
    if (!empty($tokenRes["result"]["value"])) {
        $info = $tokenRes["result"]["value"][0]["account"]["data"]["parsed"]["info"]["tokenAmount"];
        $token = $info["uiAmount"] ?? 0;
    }

    echo json_encode([
        "wallet" => $ADMIN_WALLET,
        "sol"    => $sol,
        "token"  => $token
    ]);
    exit;
}

// =========================
// SOL AIRDROP
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $now = time();
    $ip  = get_client_ip();
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);

    if (!$data || empty($data["wallet"])) {
        echo json_encode(["status" => "error", "message" => "Missing wallet"]);
        exit;
    }

    $wallet = trim($data["wallet"]);

    // 1) IP 쿨다운 활성화 시 체크
    if ($ENABLE_COOLDOWN === true) {
        $cooldowns = load_cooldowns();

        if (isset($cooldowns[$ip]) && $cooldowns[$ip] > $now) {
            $remain = $cooldowns[$ip] - $now;

            echo json_encode([
                "status"   => "error",
                "stage"    => "cooldown",
                "cooldown" => $remain,
                "message"  => "Cooldown active"
            ]);
            exit;
        }
    }

    // 2) Faucet 요청
    $res = solana_rpc("requestAirdrop", [
        $wallet,
        (int)($AIRDROP_SOL * 1e9)
    ]);

    // Faucet 제한이나 에러
    if (isset($res["error"])) {

        // 쿨다운 기능이 켜져 있을 때만 기록함
        if ($ENABLE_COOLDOWN === true) {
            $cooldowns[$ip] = $now + $COOLDOWN_SECONDS;
            save_cooldowns($cooldowns);
        }

        echo json_encode([
            "status"   => "error",
            "stage"    => "airdrop-faucet",
            "message"  => $res["error"]["message"],
        ]);
        exit;
    }

    $sig = $res["result"] ?? null;

    // 3) 성공 시에도 쿨다운 설정 (ON일 경우)
    if ($ENABLE_COOLDOWN === true) {
        $cooldowns[$ip] = $now + $COOLDOWN_SECONDS;
        save_cooldowns($cooldowns);
    }

    echo json_encode([
        "status"   => "ok",
        "explorer" => "https://explorer.solana.com/tx/$sig?cluster=devnet",
        "cooldown" => ($ENABLE_COOLDOWN ? $COOLDOWN_SECONDS : 0)
    ]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid request"]);
exit;
?>
