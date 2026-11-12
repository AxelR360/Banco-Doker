<?php

class LocalCredentials {
    public static function verify(array $user, string $password): bool {
        if (!empty($user['password_hash'])) {
            return password_verify($password, $user['password_hash']);
        }

        if (getenv('BANCO_DEMO_LOGIN') !== '1' || getenv('RENDER') === 'true') {
            return false;
        }

        $demoIds = [
            'admin' => '00000000-0000-0000-0000-000000000001',
            'asesor1' => '00000000-0000-0000-0000-000000000002',
            'axel.rosero' => '00000000-0000-0000-0000-000000000003',
            'melany.zambrano' => '00000000-0000-0000-0000-000000000004',
        ];
        return ($demoIds[$user['username']] ?? null) === $user['keycloak_id']
            && $password === '12345';
    }

    public static function validPassword(string $password): bool {
        return trim($password) !== '' && strlen($password) <= 72 && !str_contains($password, "\0");
    }
}
