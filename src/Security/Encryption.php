<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Security;

/**
 * Encryption
 *
 * Handles encryption and decryption of sensitive data using AES-256-CBC.
 * Uses WordPress AUTH_KEY as the encryption key for site-specific security.
 *
 * @package flavor\SchemaMarkupGenerator\Security
 * @author  Michele Marri <info@metodo.dev>
 */
class Encryption
{
    /**
     * Encryption method
     */
    private const METHOD = 'aes-256-cbc';

    /**
     * Get the encryption key
     * 
     * Uses WordPress AUTH_KEY which is unique per installation.
     * Falls back to a generated key if AUTH_KEY is not available.
     */
    private function getKey(): string
    {
        if (defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here') {
            return hash('sha256', AUTH_KEY . 'smg_encryption_salt');
        }

        // Fallback key (less secure, but better than nothing)
        return hash('sha256', ABSPATH . DB_NAME . 'smg_fallback_key');
    }

    /**
     * Encrypt a string
     *
     * @param string $data The data to encrypt
     * @return string|false The encrypted data (base64 encoded) or false on failure
     */
    public function encrypt(string $data): string|false
    {
        if (empty($data)) {
            return false;
        }

        $key = $this->getKey();
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        
        if ($ivLength === false) {
            return false;
        }

        $iv = openssl_random_pseudo_bytes($ivLength);
        
        if ($iv === false) {
            return false;
        }

        $encrypted = openssl_encrypt(
            $data,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            return false;
        }

        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string
     *
     * @param string $encryptedData The encrypted data (base64 encoded)
     * @return string|false The decrypted data or false on failure
     */
    public function decrypt(string $encryptedData): string|false
    {
        if (empty($encryptedData)) {
            return false;
        }

        $key = $this->getKey();
        $data = base64_decode($encryptedData, true);
        
        if ($data === false) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        
        if ($ivLength === false) {
            return false;
        }

        // Extract IV and encrypted data
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        if (strlen($iv) !== $ivLength) {
            return false;
        }

        $decrypted = openssl_decrypt(
            $encrypted,
            self::METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted;
    }

    /**
     * Check if encryption is available
     */
    public function isAvailable(): bool
    {
        return function_exists('openssl_encrypt') 
            && function_exists('openssl_decrypt')
            && in_array(self::METHOD, openssl_get_cipher_methods(), true);
    }

    /**
     * Mask a token for display
     *
     * @param string $token The token to mask
     * @param int $visibleChars Number of characters to show at the end
     * @return string The masked token
     */
    public static function mask(string $token, int $visibleChars = 4): string
    {
        if (strlen($token) <= $visibleChars) {
            return str_repeat('•', strlen($token));
        }

        return str_repeat('•', strlen($token) - $visibleChars) . substr($token, -$visibleChars);
    }
}

