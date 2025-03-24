<?php

class S3Testing_Encryption
{
    private static $key;

    public static function encrypt($string)
    {
        if (!is_string($string) || !$string) {
            return '';
        }

        if (!self::$key) {
            self::generate_key();
        }

        $nonce = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CTR'));
        $openssl_raw_data = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : 1;
        $encrypted = openssl_encrypt(
            $string,
            'AES-256-CTR',
            self::$key,
            $openssl_raw_data,
            $nonce
        );

        return '$S3Testing$OSSL$' . base64_encode($nonce . $encrypted);
    }

    public static function decrypt($string)
    {
        if (!is_string($string) || !$string) {
            return '';
        }

        if (!self::$key) {
            self::generate_key();
        }

        $no_prefix = substr($string, strlen('$S3Testing$OSSL$'));
        $encrypted = base64_decode($no_prefix);
        if ($encrypted === false) {
            return '';
        }

        $nonce_size = openssl_cipher_iv_length('AES-256-CTR');
        $nonce = substr($encrypted, 0, $nonce_size);
        $to_decrypt = substr($encrypted, $nonce_size);
        $openssl_raw_data = defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true;

        return openssl_decrypt(
            $to_decrypt,
            'AES-256-CTR',
            self::$key,
            $openssl_raw_data,
            $nonce
        );
    }

    private static function generate_key()
    {
        $default_key = DB_NAME . DB_USER . DB_PASSWORD;
        self::$key = md5((string) $default_key);
    }

}