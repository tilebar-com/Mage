<?php
namespace Mage\Mage\Core;

trait Encrypt
{

        /**
         * Get a secret key for encrypt/decrypt
         *
         * Use libsodium to generate a secret key.  This should be kept secure.
         *
         * @return string
         * @see encrypt(), decrypt()
         */
        public static function generateSecretKey()
        {
            return sodium_crypto_secretbox_keygen();
        }
    
        /**
         * Encrypt a message
         *
         * Use libsodium to encrypt a string
         *
         * @param string $message - message to encrypt
         * @param string $secret_key - encryption key
         * @param int $block_size - pad the message by $block_size byte chunks to conceal encrypted data size. must match between encrypt/decrypt!
         * @return string
         * @see decrypt()
         * @see https://github.com/jedisct1/libsodium/issues/392
         */
        public static function encrypt($message, $secret_key, $block_size = 1)
        {
            // create a nonce for this operation. it will be stored and recovered in the message itself
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    
            // pad to $block_size byte chunks (enforce 512 byte limit)
            $padded_message = sodium_pad($message, $block_size <= 512 ? $block_size : 512);
    
            // encrypt message and combine with nonce
            $cipher = base64_encode($nonce . sodium_crypto_secretbox($padded_message, $nonce, $secret_key));
    
            // cleanup
            sodium_memzero($message);
            sodium_memzero($secret_key);
    
            return $cipher;
        }
    
        /**
         * Decrypt a message
         *
         * Use libsodium to decrypt an encrypted string
         *
         * @param string $encrypted - the encrypted message
         * @param string $key - encryption key
         * @param int $block_size - pad the message by $block_size byte chunks to conceal encrypted data size. must match between encrypt/decrypt!
         * @return string
         * @see encrypt()
         * @see https://github.com/jedisct1/libsodium/issues/392
         */
        public static function decrypt($encrypted, $secret_key, $block_size = 1)
        {
            // unpack base64 message
            $decoded = base64_decode($encrypted);
    
            // check for general failures
            if ($decoded === false) {
                throw new \Exception('The encoding failed');
            }
    
            // check for incomplete message. CRYPTO_SECRETBOX_MACBYTES doesn't seem to exist in this version...
            if (!defined('CRYPTO_SECRETBOX_MACBYTES')) define('CRYPTO_SECRETBOX_MACBYTES', 16);
            if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + CRYPTO_SECRETBOX_MACBYTES)) {
                throw new \Exception('The message was truncated');
            }
    
            // pull nonce and ciphertext out of unpacked message
            $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
            $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
    
            // decrypt it and account for extra padding from $block_size (enforce 512 byte limit)
            $decrypted_padded_message = sodium_crypto_secretbox_open($ciphertext, $nonce, $secret_key);
            $message = sodium_unpad($decrypted_padded_message, $block_size <= 512 ? $block_size : 512);
    
            // check for encrpytion failures
            if ($message === false) {
                 throw new \Exception('The message was tampered with in transit');
            }
    
            // cleanup
            sodium_memzero($ciphertext);
            sodium_memzero($secret_key);
    
            return $message;
        }
    
}
