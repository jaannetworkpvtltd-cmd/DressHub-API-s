<?php

class JWT {
    private $secret = 'your-secret-key-change-this';
    private $algorithm = 'HS256';

    public function encode($data) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        $payload = json_encode($data);
        
        $header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        
        $signature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        
        return "$header.$payload.$signature";
    }

    public function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) != 3) {
            return null;
        }
        
        list($header, $payload, $signature) = $parts;
        
        $valid_signature = hash_hmac('sha256', "$header.$payload", $this->secret, true);
        $valid_signature = rtrim(strtr(base64_encode($valid_signature), '+/', '-_'), '=');
        
        if ($signature !== $valid_signature) {
            return null;
        }
        
        $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        
        // Check expiration
        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            return null;
        }
        
        return $decoded;
    }
}
