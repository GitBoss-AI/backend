<?php
namespace App\Services;

class JWTService {
    private $secret;
    private $algorithm = 'HS256';
    private $expirationTime = 3600; // 1 hour

    public function __construct() {
        $this->secret = $_ENV['JWT_SECRET'];
    }

    /**
     * Generate a JWT token for a user
     *
     * @param array $user User data including 'id' and 'username'
     * @return string The JWT token
     */
    public function generateToken(array $user): string {
        $issuedAt = time();
        $expiration = $issuedAt + $this->expirationTime;

        $payload = [
            'issuedAt' => $issuedAt,
            'expiration' => $expiration,
            'subject' => $user['id'],
            'username' => $user['username']
        ];

        return $this->encode($payload);
    }

    /**
     * Validate a JWT token
     *
     * @param string $token The JWT token to validate
     * @return array|null The payload if valid, null if invalid
     */
    public function validateToken(string $token): ?array {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            [$header_b64, $payload_b64, $signature_b64] = $parts;

            // Verify signature
            $signature = $this->base64UrlDecode($signature_b64);
            $expectedSignature = $this->sign("$header_b64.$payload_b64", $this->secret);

            if (!hash_equals($signature, $expectedSignature)) {
                return null;
            }

            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payload_b64), true);

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encode a payload into a JWT token
     *
     * @param array $payload The data to encode
     * @return string The JWT token
     */
    private function encode(array $payload): string {
        $header = ['typ' => 'JWT', 'alg' => $this->algorithm];

        $header_b64 = $this->base64UrlEncode(json_encode($header));
        $payload_b64 = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign("$header_b64.$payload_b64", $this->secret);
        $signature_b64 = $this->base64UrlEncode($signature);

        return "$header_b64.$payload_b64.$signature_b64";
    }

    /**
     * Sign a message with a secret key using HMAC SHA-256
     *
     * @param string $message The message to sign
     * @param string $secret The secret key
     * @return string The signature
     */
    private function sign(string $message, string $secret): string {
        return hash_hmac('sha256', $message, $secret, true);
    }

    /**
     * Encode a string to URL-safe base64
     *
     * @param string $data The data to encode
     * @return string The base64url encoded string
     */
    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decode a URL-safe base64 string
     *
     * @param string $data The base64url encoded string
     * @return string The decoded data
     */
    private function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Set the token expiration time in seconds
     *
     * @param int $seconds Expiration time in seconds
     * @return self
     */
    public function setExpirationTime(int $seconds): self {
        $this->expirationTime = $seconds;
        return $this;
    }
}