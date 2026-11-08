<?php

class Auth {
    private static string $secret = 'super_secret_jwt_key_banco_digital_2026';

    public static function generateToken(array $payload, int $expirySeconds = 86400): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = time();
        $payload['exp'] = time() + $expirySeconds;
        $payloadJson = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
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

        // 1. Verificación si el Token proviene de Keycloak (SSO / OIDC)
        if (isset($payload['iss']) && str_contains($payload['iss'], 'banco-digital')) {
            // Validar expiración del token Keycloak
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            // Buscar usuario local vinculado por keycloak_id (sub)
            $sub = $payload['sub'] ?? '';
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT u.id_usuario, u.username, u.rol, u.id_cliente, u.estado,
                       c.nombres, c.apellidos, c.correo
                FROM usuarios u
                LEFT JOIN clientes c ON u.id_cliente = c.id_cliente
                WHERE u.keycloak_id = :sub OR u.username = :uname
            ");
            $stmt->execute([
                'sub'   => $sub,
                'uname' => $payload['preferred_username'] ?? ''
            ]);
            $localUser = $stmt->fetch();

            if ($localUser && $localUser['estado'] === 'ACTIVO') {
                $fullName = trim(($localUser['nombres'] ?? '') . ' ' . ($localUser['apellidos'] ?? ''));
                return [
                    'id_usuario' => (int)$localUser['id_usuario'],
                    'username'   => $localUser['username'],
                    'rol'        => $localUser['rol'],
                    'id_cliente' => $localUser['id_cliente'] ? (int)$localUser['id_cliente'] : null,
                    'nombre'     => $fullName ?: ucfirst($localUser['username']),
                    'correo'     => $localUser['correo'] ?? '',
                    'source'     => 'keycloak_sso'
                ];
            }
        }

        // 2. Verificación si es un Token JWT emitido localmente
        $sig = self::base64UrlDecode($sigB64);
        $expectedSig = hash_hmac('sha256', $headerB64 . "." . $payloadB64, self::$secret, true);

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
