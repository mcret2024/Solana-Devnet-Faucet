<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$data = json_decode(file_get_contents("php://input"), true);
$wallet = $data["wallet"] ?? null;

if (!$wallet) {
    echo json_encode(["status"=>"error","message"=>"No wallet"]);
    exit;
}

/* -------- ADMIN PRIVATE KEY (Devnet) -------- */
$secret = [
 94,98,127,125,1,80,68,129,136,114,180,202,126,236,153,11,
 102,220,12,18,230,98,74,65,214,243,140,63,203,191,180,70,
 221,193,73,27,138,196,157,69,24,170,81,70,82,178,134,190,
 71,101,116,35,46,134,135,166,129,233,154,157,55,15,237,31
];
$secret_str = json_encode($secret);

/* -------- Worker-like Remote Signer (Python) --------
   Because PHP cannot sign Solana tx by itself.
   We call a remote signer (we create it).
------------------------------------------------------ */

$payload = [
    "wallet" => $wallet,
    "secret" => $secret,              // signer key
    "tokenMint" => "DUbbqANBKJqAUCJveSEFgVPGHDwkdc6d9UiQyxBLcyN3",
    "amountSol" => 0.1,
    "amountToken" => 1000
];

$ch = curl_init("https://signer.mcret.com/solana-airdrop");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
exit;
