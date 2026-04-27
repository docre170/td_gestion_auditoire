<?php
require_once 'auth.php';

demarrer_session_securisee();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['pending_user']) || empty($_SESSION['otp_verified'])) {
    echo json_encode(['ok' => false, 'error' => 'Session 2FA invalide.']);
    exit;
}

$origin = (string) (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://'
    . ($_SERVER['HTTP_HOST'] ?? 'localhost')
);
$username = (string) $_SESSION['pending_user'];
$action = $_GET['action'] ?? '';

if ($action === 'register_challenge') {
    $challenge = random_bytes(32);
    $userId = random_bytes(16);
    $_SESSION['biometric_register_challenge'] = base64_encode($challenge);

    echo json_encode([
        'ok' => true,
        'challenge' => base64_encode($challenge),
        'user_id' => base64_encode($userId),
        'username' => $username,
        'origin' => $origin,
    ]);
    exit;
}

if ($action === 'register_complete') {
    $input = json_decode(file_get_contents('php://input'), true);
    $credentialId = (string) ($input['credentialId'] ?? '');
    $clientDataJson = (string) ($input['clientDataJSON'] ?? '');
    $expectedChallenge = (string) ($_SESSION['biometric_register_challenge'] ?? '');

    if ($credentialId === '' || $clientDataJson === '' || $expectedChallenge === '') {
        echo json_encode(['ok' => false, 'error' => 'Enrolement biométrique invalide.']);
        exit;
    }

    $clientData = json_decode(base64_decode($clientDataJson, true) ?: '', true);
    if (!is_array($clientData)) {
        echo json_encode(['ok' => false, 'error' => 'ClientData biométrique invalide.']);
        exit;
    }

    if (($clientData['type'] ?? '') !== 'webauthn.create') {
        echo json_encode(['ok' => false, 'error' => 'Type WebAuthn inattendu.']);
        exit;
    }

    if (($clientData['challenge'] ?? '') !== $expectedChallenge || ($clientData['origin'] ?? '') !== $origin) {
        echo json_encode(['ok' => false, 'error' => 'Challenge ou origine non valide.']);
        exit;
    }

    if (!enregistrer_credential_utilisateur($username, $credentialId)) {
        echo json_encode(['ok' => false, 'error' => 'Impossible de sauvegarder la cle biométrique.']);
        exit;
    }

    unset($_SESSION['biometric_register_challenge']);
    echo json_encode(['ok' => true, 'registered' => true]);
    exit;
}

if ($action === 'auth_challenge') {
    $challenge = random_bytes(32);
    $credentialId = recuperer_credential_utilisateur($username);

    if ($credentialId === null) {
        echo json_encode(['ok' => false, 'error' => 'Aucune cle biométrique enregistree pour cet utilisateur.']);
        exit;
    }

    $_SESSION['biometric_auth_challenge'] = base64_encode($challenge);
    $_SESSION['biometric_auth_expires_at'] = time() + 120;

    echo json_encode([
        'ok' => true,
        'challenge' => base64_encode($challenge),
        'credentialId' => $credentialId,
        'origin' => $origin,
        'username' => $username,
    ]);
    exit;
}

if ($action === 'auth_complete') {
    $input = json_decode(file_get_contents('php://input'), true);
    $credentialId = (string) ($input['credentialId'] ?? '');
    $clientDataJson = (string) ($input['clientDataJSON'] ?? '');
    $expectedChallenge = (string) ($_SESSION['biometric_auth_challenge'] ?? '');
    $expiresAt = (int) ($_SESSION['biometric_auth_expires_at'] ?? 0);
    $storedCredentialId = recuperer_credential_utilisateur($username);

    if ($credentialId === '' || $clientDataJson === '' || $expectedChallenge === '' || $storedCredentialId === null) {
        echo json_encode(['ok' => false, 'error' => 'Verification biométrique invalide.']);
        exit;
    }

    if (time() > $expiresAt) {
        echo json_encode(['ok' => false, 'error' => 'Challenge biométrique expire.']);
        exit;
    }

    if (!hash_equals($storedCredentialId, $credentialId)) {
        echo json_encode(['ok' => false, 'error' => 'Cle biométrique non reconnue.']);
        exit;
    }

    $clientData = json_decode(base64_decode($clientDataJson, true) ?: '', true);
    if (!is_array($clientData)) {
        echo json_encode(['ok' => false, 'error' => 'ClientData biométrique invalide.']);
        exit;
    }

    if (($clientData['type'] ?? '') !== 'webauthn.get') {
        echo json_encode(['ok' => false, 'error' => 'Type WebAuthn inattendu.']);
        exit;
    }

    if (($clientData['challenge'] ?? '') !== $expectedChallenge || ($clientData['origin'] ?? '') !== $origin) {
        echo json_encode(['ok' => false, 'error' => 'Challenge ou origine non valide.']);
        exit;
    }

    $_SESSION['biometric_verified'] = true;
    unset($_SESSION['biometric_auth_challenge'], $_SESSION['biometric_auth_expires_at']);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Action inconnue.']);

