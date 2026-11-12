<?php

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private static function signingKey(): string {
        $key = getenv('BANCO_JWT_SECRET');
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('BANCO_JWT_SECRET debe tener al menos 32 caracteres');
        }
        return $key;
    }

    public static function generateToken(array $payload, int $expirySeconds = 86400): string {
        $payload['iat'] = time();
        $payload['exp'] = time() + $expirySeconds;
        return JWT::encode($payload, self::signingKey(), 'HS256');
    }

    public static function getCurrentUser(): ?array {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $header = $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/iD', $header, $matches)) {
            return null;
        }

        $token = $matches[1];
        $issuer = rtrim(getenv('KEYCLOAK_REALM_URL') ?: 'http://keycloak:8080/realms/banco-digital', '/');
        $issuers = array_map('trim', explode(',', getenv('KEYCLOAK_ALLOWED_ISSUERS') ?: $issuer));
        try {
            $segments = explode('.', $token);
            $header = json_decode(JWT::urlsafeB64Decode($segments[0]), false, 512, JSON_THROW_ON_ERROR);
            if (($header->alg ?? null) === 'RS256') {
                $context = stream_context_create(['http' => ['timeout' => 5, 'follow_location' => 0]]);
                $jwks = @file_get_contents($issuer . '/protocol/openid-connect/certs', false, $context);
                if ($jwks === false) {
                    return null;
                }
                $keys = JWK::parseKeySet(json_decode($jwks, true, 512, JSON_THROW_ON_ERROR), 'RS256');
                $claims = JWT::decode($token, array_filter($keys, fn(Key $key) => $key->getAlgorithm() === 'RS256'));
                if (!in_array($claims->iss ?? null, $issuers, true)
                    || ($claims->azp ?? null) !== 'banco-frontend'
                    || !in_array('banco-api', (array)($claims->aud ?? []), true)
                    || !is_string($claims->sub ?? null)) {
                    return null;
                }
                $column = 'keycloak_id';
                $identity = $claims->sub;
            } else {
                $claims = JWT::decode($token, new Key(self::signingKey(), 'HS256'));
                $column = 'id_usuario';
                $identity = $claims->id_usuario ?? null;
            }
            if (!isset($claims->exp) || !$identity) {
                return null;
            }
        } catch (Exception $error) {
            return null;
        }

        $stmt = Database::getConnection()->prepare(
            "SELECT id_usuario, username, rol, id_cliente FROM usuarios
             WHERE {$column} = :identity AND estado = 'ACTIVO'"
        );
        $stmt->execute(['identity' => $identity]);
        $user = $stmt->fetch();
        if (!$user || ($user['rol'] === 'CLIENTE' && !$user['id_cliente'])) {
            return null;
        }
        return $user;
    }

    public static function requireAuth(array $allowedRoles = []): array {
        $user = self::getCurrentUser();
        if (!$user) {
            Response::error('No autenticado. Token JWT requerido o expirado.', 401);
        }
        if ($allowedRoles && !in_array($user['rol'], $allowedRoles, true)) {
            Response::error('Acceso denegado para este rol', 403);
        }
        return $user;
    }
}
