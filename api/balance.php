<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

/* ADMIN WALLET */
$admin_wallet = "Fve3Yqxyuj5GTLK4YaECADPdzcrRVovtsVFnDuY7n35g";
$mint = "DUbbqANBKJqAUCJveSEFgVPGHDwkdc6d9UiQyxBLcyN3";

/* Query Devnet Explorer API */
$sol = file_get_contents("https://api.solana.fm/v0/accounts/$admin_wallet?cluster=devnet");
$sol_json = json_decode($sol, true);
$sol_balance = $sol_json["result"]["lamports"] / 1e9;

/* Token balance (simple) */
$token = file_get_contents("https://api.solana.fm/v0/accounts/$admin_wallet/tokens?cluster=devnet");
$t_json = json_decode($token, true);

$token_amount = 0;
foreach ($t_json["result"] as $t) {
    if ($t["mint"] === $mint) {
        $token_amount = $t["amount"];
        break;
    }
}

echo json_encode([
    "admin_wallet" => $admin_wallet,
    "sol" => $sol_balance,
    "token" => $token_amount
]);
exit;
