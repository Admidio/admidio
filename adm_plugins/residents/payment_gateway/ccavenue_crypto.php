<?php
/**
    * CCAvenue encryption/decryption functions
    */

function encrypt_ccavenue($plainText, $workingKey) {
    $key = md5($workingKey, true); // 16 bytes, raw binary
    $iv = pack("C*", 0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
    $encrypted = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return bin2hex($encrypted);
}

function decrypt_ccavenue($encryptedText, $workingKey) {
    $key = md5($workingKey, true);
    $iv = pack("C*", 0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
    $encryptedText = hex2bin($encryptedText);
    return openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
}
?>