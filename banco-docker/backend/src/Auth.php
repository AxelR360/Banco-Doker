<?php

class Auth {
    private static function signingKey(): string {
        $key = getenv('BANCO_JWT_SECRET');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('BANCO_JWT_SECRET requires at least 32 characters.');
        }
        return $key;
    }

    public static function generateToken(array $payload, int $expirySeconds = 86400): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + $expirySeconds;
        $payloadJson = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::signingKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function getCurrentUser(): ?array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);

        if (!$payload) {
            return null;
        }

        $issuer = rtrim(getenv('KEYCLOAK_REALM_URL') ?: '', '/');
        if ($issuer !== '' && ($payload['iss'] ?? null) === $issuer) {
            try {
                $context = stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 0]]);
                $jwks = @file_get_contents($issuer . '/protocol/openid-connect/certs', false, $context);
                if ($jwks === false) {
                    return null;
                }
                $keys = \Firebase\JWT\JWK::parseKeySet(json_decode($jwks, true, 512, JSON_THROW_ON_ERROR), 'RS256');
                $claims = \Firebase\JWT\JWT::decode($token, $keys);
            } catch (\Exception $error) {
                return null;
            }

            if (($claims->iss ?? null) !== $issuer
                || !in_array('banco-api', (array) ($claims->aud ?? []), true)
                || ($claims->azp ?? null) !== 'banco-frontend'
                || !isset($claims->exp)
                || empty($claims->sub)) {
                return null;
            }

            $stmt = Database::getConnection()->prepare(
                "SELECT id_usuario, username, rol, id_cliente
                 FROM usuarios WHERE keycloak_id = :sub AND estado = 'ACTIVO'"
            );
            $stmt->execute(['sub' => $claims->sub]);
            return $stmt->fetch() ?: null;
        }

        // Verificación si es un Token JWT emitido localmente
        $sig = self::base64UrlDecode($sigB64);
        $expectedSig = hash_hmac('sha256', $headerB64 . "." . $payloadB64, self::signingKey(), true);

        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    public static function requireAuth(array $allowedRoles = []): array {
        $user = self::getCurrentUser();
        if (!$user) {
            Response::error('No autenticado. Token JWT / Keycloak OIDC requerido o expirado.', 401);
        }

        if (!empty($allowedRoles) && !in_array($user['rol'], $allowedRoles, true)) {
            Response::error('Acceso denegado: rol no autorizado (' . $user['rol'] . ')', 403);
        }

        return $user;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
