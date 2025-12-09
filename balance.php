<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$rpc = "https://api.devnet.solana.com";

/* ---- ADMIN WALLET & TOKEN CONFIG ---- */
$admin_wallet = "Fve3Yqxyuj5GTLK4YaECADPdzcrRVovtsVFnDuY7n35g";
$token_ata    = "3TdJVrWfiEvcpKNaytDgGUko1RBSgBNYDgfvcpVUc3fy"; // Admin Token-2022 ATA
$token_mint   = "DUbbqANBKJqAUCJveSEFgVPGHDwkdc6d9UiQyxBLcyN3";

/* ================================================
   GET ADMIN SOL BALANCE
================================================ */
$payload_sol = [
    "jsonrpc" => "2.0",
    "id"      => 1,
    "method"  => "getBalance",
    "params"  => [$admin_wallet]
];

$ch = curl_init($rpc);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_sol));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type" => "application/json"]);
$res_sol = json_decode(curl_exec($ch), true);

$admin_sol = isset($res_sol["result"]["value"])
    ? $res_sol["result"]["value"] / 1e9
    : 0;

/* ================================================
   GET TOKEN BALANCE FROM ATA DIRECTLY (Token-2022)
================================================ */
$payload_token = [
    "jsonrpc" => "2.0",
    "id"      => 2,
    "method"  => "getAccountInfo",
    "params"  => [
        $token_ata,
        ["encoding" => "jsonParsed"]
    ]
];

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_token));
$res_token = json_decode(curl_exec($ch), true);

$token_amount = 0;

if (!empty($res_token["result"]["value"])) {
    $parsed = $res_token["result"]["value"]["data"]["parsed"]["info"]["tokenAmount"];
    $token_amount = $parsed["uiAmount"];
}

curl_close($ch);

/* ================================================
   OUTPUT
================================================ */
echo json_encode([
    "admin_wallet" => $admin_wallet,
    "token_account" => $token_ata,
    "sol" => $admin_sol,
    "token" => $token_amount
]);
