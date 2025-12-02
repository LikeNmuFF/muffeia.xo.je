<?php
// Encryption functions for end-to-end encryption
class MessageEncryption {
    
    // Simple symmetric encryption (using AES-256-CBC)
    public static function encryptMessage($message, $key) {
        // Validate key length
        if (strlen($key) !== 64) {
            throw new Exception('Encryption key must be 64 characters (32 bytes hex)');
        }
        
        $key = hex2bin($key); // Convert hex to binary
        $iv = random_bytes(16); // 128-bit IV for AES
        
        // Encrypt the message
        $encrypted = openssl_encrypt(
            $message, 
            'AES-256-CBC', 
            $key, 
            OPENSSL_RAW_DATA, 
            $iv
        );
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }
        
        // Combine IV and encrypted data
        $result = base64_encode($iv . $encrypted);
        return $result;
    }
    
    public static function decryptMessage($encryptedData, $key) {
        // Validate key length
        if (strlen($key) !== 64) {
            throw new Exception('Decryption key must be 64 characters (32 bytes hex)');
        }
        
        $key = hex2bin($key); // Convert hex to binary
        
        // Decode the base64 data
        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new Exception('Invalid base64 data');
        }
        
        // Extract IV (first 16 bytes) and encrypted data
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        if (strlen($iv) !== 16) {
            throw new Exception('Invalid IV length');
        }
        
        // Decrypt the message
        $decrypted = openssl_decrypt(
            $encrypted, 
            'AES-256-CBC', 
            $key, 
            OPENSSL_RAW_DATA, 
            $iv
        );
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed: ' . openssl_error_string());
        }
        
        return $decrypted;
    }
    
    // Generate a random encryption key
    public static function generateEncryptionKey() {
        return bin2hex(random_bytes(32)); // 64-character hex string
    }
    
    // Generate shared key for conversation (deterministic based on conversation ID)
    public static function generateConversationKey($conversation_id) {
        // Create a deterministic key based on conversation ID
        // This ensures both users in the conversation generate the same key
        $seed = "muffeia_conversation_{$conversation_id}_encryption_key_2024";
        
        // Use hash function to derive a consistent 64-character key
        $key = hash('sha256', $seed);
        
        return $key;
    }
    
    // Verify if a string is encrypted (basic check)
    public static function isEncrypted($data) {
        if (empty($data)) return false;
        
        // Check if it's base64 encoded and has minimum length for IV + some data
        if (base64_decode($data, true) === false) {
            return false;
        }
        
        $decoded = base64_decode($data);
        return strlen($decoded) >= 32; // IV (16) + minimum encrypted data (16)
    }
}
?>