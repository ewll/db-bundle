<?php namespace Ewll\DBBundle\Annotation;

use RuntimeException;

/** @Annotation */
class CipheredType extends TypeAbstract
{
    private const CIPHER_ALGORITHM = 'AES-128-CBC';
    private const HMAC_ALGORITHM = 'sha256';
    private const HMAC_RAW_OUTPUT = true;
    private const SHA_2_LEN = 32;

    public function transformToView($value, array $options)
    {
        if (empty($options['cipherkey'])) {
            throw new RuntimeException('Empty Cipher Key');
        }

        if (null === $value) {
            return null;
        }

        //@TODO remove after migrate
        if ($value[0] === '{') {
            return json_decode($value, true);
        }

        $value = base64_decode($value);
        $ivlen = openssl_cipher_iv_length(self::CIPHER_ALGORITHM);
        $iv = substr($value, 0, $ivlen);
        $hmac = substr($value, $ivlen, self::SHA_2_LEN);
        $cipherValueRaw = substr($value, $ivlen + self::SHA_2_LEN);
        $originalValue = openssl_decrypt(
            $cipherValueRaw,
            self::CIPHER_ALGORITHM,
            $options['cipherkey'],
            OPENSSL_RAW_DATA,
            $iv
        );
        $calcmac = hash_hmac('sha256', $cipherValueRaw, $options['cipherkey'], self::HMAC_RAW_OUTPUT);
        if (!hash_equals($hmac, $calcmac)) {
            throw new RuntimeException('Time attack');
        }

        return json_decode($originalValue, true);
    }

    public function transformToStore($value, array $options)
    {
        if (empty($options['cipherkey'])) {
            throw new RuntimeException('Empty Cipher Key');
        }

        if (null === $value) {
            return null;
        }

        $value = json_encode($value);

        $ivlen = openssl_cipher_iv_length(self::CIPHER_ALGORITHM);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $cipherValueRaw = openssl_encrypt($value, self::CIPHER_ALGORITHM, $options['cipherkey'], OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac(self::HMAC_ALGORITHM, $cipherValueRaw, $options['cipherkey'], self::HMAC_RAW_OUTPUT);
        $cipheredValue = base64_encode($iv.$hmac.$cipherValueRaw);

        return $cipheredValue;
    }
}
