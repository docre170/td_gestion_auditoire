<?php

function demarrer_session_securisee(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function utilisateur_autorise(string $username, string $password): bool {
    $utilisateurs = [
        'admin' => 'upc123',
    ];
    return isset($utilisateurs[$username]) && hash_equals($utilisateurs[$username], $password);
}

function generer_otp(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function webauthn_storage_path(): string {
    return __DIR__ . '/data/webauthn_users.json';
}

function charger_webauthn_users(): array {
    $path = webauthn_storage_path();
    if (!is_file($path)) {
        return [];
    }

    $raw = (string) file_get_contents($path);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sauvegarder_webauthn_users(array $users): bool {
    $path = webauthn_storage_path();
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        return false;
    }

    return file_put_contents($path, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function enregistrer_credential_utilisateur(string $username, string $credentialIdB64Url): bool {
    $users = charger_webauthn_users();
    $users[$username] = [
        'credential_id' => $credentialIdB64Url,
        'updated_at' => date(DATE_ATOM),
    ];
    return sauvegarder_webauthn_users($users);
}

function recuperer_credential_utilisateur(string $username): ?string {
    $users = charger_webauthn_users();
    if (!isset($users[$username]) || !is_array($users[$username])) {
        return null;
    }
    $value = (string) ($users[$username]['credential_id'] ?? '');
    return $value !== '' ? $value : null;
}

function exiger_authentification_complete(): void {
    demarrer_session_securisee();
    if (empty($_SESSION['authenticated'])) {
        header('Location: login.php');
        exit;
    }
}

function deconnexion_complete(): void {
    demarrer_session_securisee();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

