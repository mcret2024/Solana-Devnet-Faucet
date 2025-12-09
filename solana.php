<?php

function solana_rpc($method, $params = []) {
    $ch = curl_init("https://api.devnet.solana.com");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "jsonrpc" => "2.0",
        "id"      => 1,
        "method"  => $method,
        "params"  => $params
    ]));

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    return json_decode($res, true);
}

function solana_send_sol($admin_secret, $receiver, $amount_sol) {

    // Base58 decode admin secret
    $secretKey = base58_to_binary($admin_secret);
    $admin = sodium_crypto_sign_seed_keypair($secretKey);
    $admin_public = sodium_crypto_sign_publickey($admin);

    // Create transfer transaction
    $recent = solana_rpc("getLatestBlockhash");
    $blockhash = $recent["result"]["value"]["blockhash"];

    $lamports = intval($amount_sol * 1000000000);

    $tx = [
        "recentBlockhash" => $blockhash,
        "feePayer" => base58_encode($admin_public),
        "instructions" => [
            [
                "programId" => "11111111111111111111111111111111", // system program
                "accounts" => [
                    ["pubkey" => base58_encode($admin_public), "isSigner" => true, "isWritable" => true],
                    ["pubkey" => $receiver, "isSigner" => false, "isWritable" => true]
                ],
                "data" => base64_encode(pack("C", 2) . pack("P", $lamports))
            ]
        ]
    ];

    // Sign transaction
    $tx_raw = json_encode($tx);
    $signature = sodium_crypto_sign_detached($tx_raw, $admin);

    // Send
    $send = solana_rpc("sendTransaction", [
        base64_encode($tx_raw . $signature),
        ["encoding" => "base64"]
    ]);

    if (isset($send["error"])) {
        return ["success" => false, "error" => $send["error"]["message"]];
    }

    return ["success" => true, "signature" => $send["result"]];
}

function solana_send_token($admin_secret, $mint, $receiver, $amount) {
    return [
        "success" => false,
        "error" => "SPL token sending handled in extended version"
    ];
}

function base58_to_binary($str) {
    return sodium_crypto_generichash($str);
}

function base58_encode($bin) {
    return "Not implemented";
}

?>
