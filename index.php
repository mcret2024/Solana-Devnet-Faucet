<?php
// =======================================================
// CONFIGURATION
// =======================================================
define('SOLANA_RPC_URL', 'https://api.devnet.solana.com');

// Admin wallet public key
define('ADMIN_PUBLIC', 'Fve3Yqxyuj5GTLK4YaECADPdzcrRVovtsVFnDuY7n35g');

// Admin wallet private key (Devnet, Uint8Array)
define('ADMIN_SECRET_JSON', json_encode([
  94,98,127,125,1,80,68,129,136,114,180,202,126,236,153,11,
  102,220,12,18,230,98,74,65,214,243,140,63,203,191,180,70,
  221,193,73,27,138,196,157,69,24,170,81,70,82,178,134,190,
  71,101,116,35,46,134,135,166,129,233,154,157,55,15,237,31
]));

// Token-2022 mint
define('TOKEN_MINT', 'DUbbqANBKJqAUCJveSEFgVPGHDwkdc6d9UiQyxBLcyN3');
define('TOKEN_DECIMALS', 6);

// Airdrop amounts
define('AIRDROP_SOL', 0.1);
define('AIRDROP_TOKEN', 1000);

// =======================================================
// IP-BASED COOLDOWN (24 hours)
// =======================================================
$COOLDOWN_SECONDS = 86400;                  // 24h
$EXEMPT_IP        = "115.88.85.189";        // Unlimited for this IP
$COOLDOWN_FILE    = __DIR__ . "/airdrop_limit.json";

// Helper: get client IP
function get_client_ip() {
    $keys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $arr = explode(",", $_SERVER[$key]);
            return trim($arr[0]);
        }
    }
    return "unknown";
}

// Load cooldown JSON
function load_cooldowns($file){
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Save cooldown JSON
function save_cooldowns($file, $arr){
    file_put_contents($file, json_encode($arr, JSON_PRETTY_PRINT));
}

// =======================================================
// Cooldown save endpoint (AJAX from JS)
// =======================================================
if (isset($_GET["saveCooldown"]) && $_GET["saveCooldown"] === "1") {
    $ip = $_GET["ip"] ?? "";
    if ($ip && $ip !== $EXEMPT_IP) {
        $cool = load_cooldowns($COOLDOWN_FILE);
        $cool[$ip] = time() + $COOLDOWN_SECONDS;
        save_cooldowns($COOLDOWN_FILE, $cool);
    }
    echo "OK";
    exit;
}

// =======================================================
// Evaluate cooldown for current user
// =======================================================
$USER_IP        = get_client_ip();
$now            = time();
$cooldowns      = load_cooldowns($COOLDOWN_FILE);
$can_airdrop    = true;
$remain_seconds = 0;

if ($USER_IP !== $EXEMPT_IP) {
    if (isset($cooldowns[$USER_IP]) && $cooldowns[$USER_IP] > $now) {
        $can_airdrop    = false;
        $remain_seconds = $cooldowns[$USER_IP] - $now;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Solana Devnet MEA Airdrop</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://unpkg.com/@solana/web3.js@latest/lib/index.iife.js"></script>

<style>
:root {
  --bg:#020617;
  --card:#0f172a;
  --border:#1e293b;
  --text:#e2e8f0;
  --text-soft:#94a3b8;
  --accent:#38bdf8;
  --danger:#f97373;
  --success:#4ade80;
}

/* Layout */
body {
  margin:0;
  font-family:system-ui,sans-serif;
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  display:flex;
  justify-content:center;
  align-items:center;
  padding:24px;
}

.card {
  width:100%;
  max-width:520px;
  background:var(--card);
  border-radius:20px;
  border:1px solid var(--border);
  padding:24px;
  box-shadow:0 0 40px rgba(0,0,0,0.6);
}

/* Skeleton loader */
.skel {
  height:14px;
  width:100%;
  background:linear-gradient(90deg,#1e293b,#334155,#1e293b);
  background-size:200% 100%;
  border-radius:6px;
  animation:shimmer 1.4s infinite;
}
@keyframes shimmer {
  0% {background-position:-200% 0;}
  100% {background-position:200% 0;}
}

/* Inputs & buttons */
input {
  width:100%;
  padding:11px;
  border-radius:10px;
  border:1px solid var(--border);
  background:#020617;
  color:var(--text);
  margin-top:4px;
}
button {
  width:100%;
  padding:12px;
  border-radius:10px;
  border:none;
  margin-top:10px;
  font-size:14px;
  cursor:pointer;
  transition:0.2s;
}
button:disabled {
  opacity:0.35;
  cursor:not-allowed;
}
.btn-connect {
  background:linear-gradient(135deg,#8b5cf6,#6366f1);
  color:#fff;
}
.btn-airdrop {
  background:linear-gradient(135deg,#22d3ee,#22c55e);
  color:#022c22;
}

/* Status text */
.status {
  min-height:22px;
  margin-top:12px;
  font-size:13px;
}
.status.ok {color:var(--success);}
.status.err{color:var(--danger);}

.cooldown-box {
  margin-top:8px;
  font-size:13px;
  color:var(--accent);
}

/* Bug box */
.bug-box {
  margin-top:18px;
  padding:14px;
  border-radius:10px;
  background:#020617;
  border:1px solid var(--border);
  font-size:13px;
}
.bug-box a {
  color:var(--accent);
  text-decoration:none;
}
</style>
</head>

<body>

<div class="card">
    <p style="color:var(--text-soft);font-size:14px;"> It will take some time to connect to DevNet. Please wait up to 30 seconds. </p>
  <h2>Solana Devnet Airdrop</h2>
  <p style="color:var(--text-soft);font-size:14px;">
    wallet sends <b><?= AIRDROP_SOL ?> SOL</b> + <b><?= AIRDROP_TOKEN ?> MEA(DUbb)</b>
  </p>

  <div style="margin-top:16px;font-size:13px;">Pool Wallet</div>
  <div id="adminWallet" class="skel"></div>

  <div style="margin-top:12px;font-size:13px;">Pool Balance</div>
  <div id="adminBalance" class="skel"></div>

  <div style="margin-top:18px;">
    <label style="font-size:13px;">Your Wallet</label>
    <input id="wallet" placeholder="Enter wallet address">
  </div>

  <button id="btnPhantom" class="btn-connect" disabled>Connect Phantom</button>
  <button id="btnAirdrop" class="btn-airdrop" disabled>Get Airdrop</button>

  <div id="status" class="status"></div>
  <div id="cooldownBox" class="cooldown-box"></div>

  <div class="bug-box">
    Bug Report / Bug Bounty → 
    <a href="https://t.me/MeccaGlobalChat/1068632" target="_blank">Open Telegram</a>
  </div>
</div>

<script>
// ===========================
// PHP → JS constants
// ===========================
const RPC_URL         = "<?= SOLANA_RPC_URL ?>";
const ADMIN_SECRET    = new Uint8Array(<?= ADMIN_SECRET_JSON ?>);
const ADMIN_PUBLIC    = "<?= ADMIN_PUBLIC ?>";
const TOKEN_MINT_STR  = "<?= TOKEN_MINT ?>";
const TOKEN_DECIMALS  = <?= TOKEN_DECIMALS ?>;
const AIRDROP_SOL     = <?= AIRDROP_SOL ?>;
const AIRDROP_TOKEN   = <?= AIRDROP_TOKEN ?>;
const USER_IP         = "<?= $USER_IP ?>";
const EXEMPT_IP       = "<?= $EXEMPT_IP ?>";
const CAN_AIRDROP     = <?= $can_airdrop ? 'true' : 'false' ?>;
const COOLDOWN_REMAIN = <?= $remain_seconds ?>;
const COOLDOWN_SECONDS= <?= $COOLDOWN_SECONDS ?>;

// ===========================
// Solana setup
// ===========================
const connection   = new solanaWeb3.Connection(RPC_URL, "confirmed");
const adminKeypair = solanaWeb3.Keypair.fromSecretKey(ADMIN_SECRET);
const adminPubkey  = adminKeypair.publicKey;
const TOKEN_MINT   = new solanaWeb3.PublicKey(TOKEN_MINT_STR);

const TOKEN_PROGRAM_ID = new solanaWeb3.PublicKey(
  "TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb"
);
const ASSOCIATED_TOKEN_PROGRAM_ID = new solanaWeb3.PublicKey(
  "ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL"
);

// ===========================
// DOM elements
// ===========================
const adminWalletEl  = document.getElementById("adminWallet");
const adminBalanceEl = document.getElementById("adminBalance");
const btnAirdrop     = document.getElementById("btnAirdrop");
const btnPhantom     = document.getElementById("btnPhantom");
const statusEl       = document.getElementById("status");
const cooldownEl     = document.getElementById("cooldownBox");

function setStatus(msg, type="") {
  statusEl.className = "status " + type;
  statusEl.innerHTML = msg;
}

// ===========================
// Cooldown display
// ===========================
function startCooldown(sec) {
  if (sec <= 0) {
    cooldownEl.innerHTML = "";
    return;
  }
  let remain = sec;
  const update = () => {
    if (remain < 0) return;
    const h = Math.floor(remain / 3600);
    const m = Math.floor((remain % 3600) / 60);
    const s = remain % 60;
    cooldownEl.innerHTML = `This IP can request the next airdrop in ${h}h ${m}m ${s}s.`;
    remain--;
  };
  update();
  const timer = setInterval(() => {
    if (remain < 0) { clearInterval(timer); return; }
    update();
  }, 1000);
}

// 초기 쿨다운 상태 반영
if (!CAN_AIRDROP && USER_IP !== EXEMPT_IP) {
  startCooldown(COOLDOWN_REMAIN);
}

// ===========================
// Load admin info (skeleton → real)
// ===========================
async function loadAdmin() {
  try {
    const lamports = await connection.getBalance(adminPubkey);
    const sol = lamports / 1e9;

    const tokAcc = await connection.getParsedTokenAccountsByOwner(
      adminPubkey,
      { mint: TOKEN_MINT }
    );

    let tokens = 0;
    if (tokAcc.value.length > 0) {
      tokens = tokAcc.value[0].account.data.parsed.info.tokenAmount.uiAmount;
    }

    adminWalletEl.textContent = adminPubkey.toString();
    adminWalletEl.classList.remove("skel");

    adminBalanceEl.textContent = `SOL: ${sol} | TOKEN: ${tokens}`;
    adminBalanceEl.classList.remove("skel");

    // Admin info loaded → enable buttons
    btnPhantom.disabled = false;
    btnAirdrop.disabled = false;

  } catch (e) {
    adminBalanceEl.textContent = "Failed to load admin balance";
    adminBalanceEl.classList.remove("skel");
    console.error(e);
  }
}
loadAdmin();

// ===========================
// Phantom connect
// ===========================
async function getPhantomProvider() {
  if (window.phantom?.solana?.isPhantom) return window.phantom.solana;
  return null;
}

btnPhantom.onclick = async () => {
  const provider = await getPhantomProvider();
  if (!provider) {
    setStatus("Phantom wallet not installed.", "err");
    return;
  }
  try {
    const resp = await provider.connect();
    document.getElementById("wallet").value = resp.publicKey.toString();
    setStatus("Phantom connected.", "ok");
  } catch (e) {
    setStatus("Failed to connect Phantom.", "err");
  }
};

// ===========================
// SOL transfer (admin → user)
// ===========================
async function sendSOL(to) {
  const tx = new solanaWeb3.Transaction().add(
    solanaWeb3.SystemProgram.transfer({
      fromPubkey: adminPubkey,
      toPubkey: new solanaWeb3.PublicKey(to),
      lamports: Math.floor(AIRDROP_SOL * 1e9)
    })
  );
  return await solanaWeb3.sendAndConfirmTransaction(
    connection,
    tx,
    [adminKeypair]
  );
}

// ===========================
// Token-2022 transfer
// ===========================
async function getATA(owner, mint) {
  const [ata] = await solanaWeb3.PublicKey.findProgramAddress(
    [owner.toBuffer(), TOKEN_PROGRAM_ID.toBuffer(), mint.toBuffer()],
    ASSOCIATED_TOKEN_PROGRAM_ID
  );
  return ata;
}

function createATAInstruction(payer, ata, owner, mint) {
  return new solanaWeb3.TransactionInstruction({
    programId: ASSOCIATED_TOKEN_PROGRAM_ID,
    keys: [
      { pubkey: payer,  isSigner: true,  isWritable: true },
      { pubkey: ata,    isSigner: false, isWritable: true },
      { pubkey: owner,  isSigner: false, isWritable: false },
      { pubkey: mint,   isSigner: false, isWritable: false },
      { pubkey: solanaWeb3.SystemProgram.programId, isSigner: false, isWritable: false },
      { pubkey: TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
      { pubkey: solanaWeb3.SYSVAR_RENT_PUBKEY, isSigner: false, isWritable: false },
    ],
    data: new Uint8Array([])
  });
}

function createTokenTransferInstruction(source, dest, owner) {
  const amount = BigInt(AIRDROP_TOKEN) * (10n ** BigInt(TOKEN_DECIMALS));
  const data = new Uint8Array(9);
  data[0] = 3; // Transfer instruction
  new DataView(data.buffer).setBigUint64(1, amount, true);

  return new solanaWeb3.TransactionInstruction({
    programId: TOKEN_PROGRAM_ID,
    keys: [
      { pubkey: source, isSigner: false, isWritable: true },
      { pubkey: dest,   isSigner: false, isWritable: true },
      { pubkey: owner,  isSigner: true,  isWritable: false },
    ],
    data
  });
}

async function sendTOKEN(to) {
  const userPk = new solanaWeb3.PublicKey(to);

  const adminATA = await getATA(adminPubkey, TOKEN_MINT);
  const userATA  = await getATA(userPk, TOKEN_MINT);

  const adminInfo = await connection.getAccountInfo(adminATA);
  const userInfo  = await connection.getAccountInfo(userATA);

  const tx = new solanaWeb3.Transaction();

  if (!adminInfo) tx.add(createATAInstruction(adminPubkey, adminATA, adminPubkey, TOKEN_MINT));
  if (!userInfo)  tx.add(createATAInstruction(adminPubkey, userATA,  userPk,    TOKEN_MINT));

  tx.add(createTokenTransferInstruction(adminATA, userATA, adminPubkey));

  return await solanaWeb3.sendAndConfirmTransaction(
    connection,
    tx,
    [adminKeypair]
  );
}

// ===========================
// Airdrop button handler
// ===========================
btnAirdrop.onclick = async () => {
  const wallet = document.getElementById("wallet").value.trim();

  if (!wallet) {
    setStatus("Enter a valid wallet address.", "err");
    return;
  }

  // Cooldown check (except exempt IP)
  if (!CAN_AIRDROP && USER_IP !== EXEMPT_IP) {
    setStatus("This IP is currently under cooldown.", "err");
    startCooldown(COOLDOWN_REMAIN);
    return;
  }

  btnAirdrop.disabled = true;
  setStatus("Sending SOL from admin wallet…");

  try {
    const solSig = await sendSOL(wallet);

    setStatus("Sending Token-2022 from admin wallet…");
    const tokSig = await sendTOKEN(wallet);

    setStatus(`
      <b>Airdrop Successful!</b><br>
      <a href="https://explorer.solana.com/tx/${solSig}?cluster=devnet"
         target="_blank" style="color:#38bdf8;">View SOL Tx</a><br>
      <a href="https://explorer.solana.com/tx/${tokSig}?cluster=devnet"
         target="_blank" style="color:#38bdf8;">View TOKEN Tx</a>
    `, "ok");

    // Save cooldown for non-exempt IPs
    if (USER_IP !== EXEMPT_IP) {
      fetch(location.pathname + "?saveCooldown=1&ip=" + encodeURIComponent(USER_IP));
      startCooldown(COOLDOWN_SECONDS);
    }

  } catch (e) {
    console.error(e);
    setStatus("Airdrop failed: " + e.message, "err");
  }

  btnAirdrop.disabled = false;
};
</script>
</body>
</html>
