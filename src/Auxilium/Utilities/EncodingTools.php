<?php

namespace Auxilium\Utilities;

class EncodingTools
{
    public static function Base64EncodeURLSafe($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function Base64DecodeURLSafe($data): false|string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }


    public static function PackUUID($uuid_string): false|string
    {
        return hex2bin(str_replace('-', '', $uuid_string));
    }

    public static function UnpackUUID($uuid_bin): string
    {
        $uuid_array = bin2hex($uuid_bin);
        $uuid_string = substr($uuid_array, 0, 8) . "-" . substr($uuid_array, 8, 4) . "-" . substr($uuid_array, 12, 4) . "-" . substr($uuid_array, 16, 4) . "-" . substr($uuid_array, 20, 32);
        return $uuid_string;
    }

    public static function GenerateNewUUID(): string
    {
        $data = openssl_random_pseudo_bytes(16); // Use openssl rand as mt_rand is known to produce duplicates.

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        return $uuid;
    }

    public static function RC2047Encode($string): string
    {
        return addcslashes(mb_encode_mimeheader($string, "UTF-8", "Q"), '"');
    }
}
