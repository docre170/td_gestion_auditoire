<?php
require_once 'auth.php';

demarrer_session_securisee();

if (empty($_SESSION['pending_user'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['otp_code'])) {
    $submittedOtp = trim((string) ($_POST['otp_code'] ?? ''));
    $expectedOtp = (string) ($_SESSION['otp_code'] ?? '');
    $expiresAt = (int) ($_SESSION['otp_expires_at'] ?? 0);

    if (time() > $expiresAt) {
        $error = "Le code OTP a expire. Reconnectez-vous.";
    } elseif (!hash_equals($expectedOtp, $submittedOtp)) {
        $error = "Code OTP invalide.";
    } else {
        $_SESSION['otp_verified'] = true;
        $success = "OTP valide. Validez maintenant la biometrie.";
    }
}

if (!empty($_SESSION['otp_verified']) && !empty($_SESSION['biometric_verified'])) {
    $_SESSION['authenticated'] = true;
    unset($_SESSION['otp_code'], $_SESSION['otp_expires_at'], $_SESSION['pending_user']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Double authentification</title>
    <link rel="stylesheet" href="./assets/css/auth.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card">
            <h1>Double authentification</h1>
            <p>Etape 1: OTP. Etape 2: verification biométrique (WebAuthn).</p>

            <div class="alert alert-info">
                Code OTP demo: <strong><?= htmlspecialchars((string) ($_SESSION['otp_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>

            <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label for="otp_code">Code OTP</label>
                <input id="otp_code" name="otp_code" type="text" minlength="6" maxlength="6" required>
                <button type="submit">Verifier OTP</button>
            </form>

            <hr>

            <div class="bio-block">
                <button id="bioButton" type="button" <?= empty($_SESSION['otp_verified']) ? 'disabled' : ''; ?>>
                    Valider par biometrie
                </button>
                <p id="bioStatus" class="hint">
                    <?= empty($_SESSION['otp_verified']) ? 'Validez d abord le code OTP.' : 'Cliquez pour enrôler/verifier empreinte ou Face ID.'; ?>
                </p>
            </div>

            <a class="link" href="logout.php">Annuler et se deconnecter</a>
        </section>
    </main>

    <script>
        const button = document.getElementById('bioButton');
        const statusEl = document.getElementById('bioStatus');

        function toBase64Url(bytes) {
            const binary = String.fromCharCode(...bytes);
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
        }

        function fromBase64(input) {
            return Uint8Array.from(atob(input), c => c.charCodeAt(0));
        }

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            return response.json();
        }

        button?.addEventListener('click', async () => {
            try {
                statusEl.textContent = 'Demarrage verification biométrique...';
                const authChallenge = await postJson('biometric_verify.php?action=auth_challenge', {});

                if (!authChallenge.ok) {
                    statusEl.textContent = 'Aucune cle biométrique trouvee. Enrolement en cours...';
                    const registerChallenge = await postJson('biometric_verify.php?action=register_challenge', {});
                    if (!registerChallenge.ok) {
                        statusEl.textContent = registerChallenge.error || 'Impossible de preparer la biometrie.';
                        return;
                    }

                    const registerCredential = await navigator.credentials.create({
                        publicKey: {
                            challenge: fromBase64(registerChallenge.challenge),
                            rp: { name: 'Gestion Auditoire UPC' },
                            user: {
                                id: fromBase64(registerChallenge.user_id),
                                name: registerChallenge.username,
                                displayName: registerChallenge.username
                            },
                            pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
                            timeout: 60000,
                            authenticatorSelection: {
                                authenticatorAttachment: 'platform',
                                userVerification: 'required'
                            },
                            attestation: 'none'
                        }
                    });

                    if (!registerCredential) {
                        statusEl.textContent = 'Enrolement biométrique annule.';
                        return;
                    }

                    const registerComplete = await postJson('biometric_verify.php?action=register_complete', {
                        credentialId: registerCredential.id,
                        clientDataJSON: toBase64Url(new Uint8Array(registerCredential.response.clientDataJSON))
                    });

                    if (!registerComplete.ok) {
                        statusEl.textContent = registerComplete.error || 'Echec de l enrolement biométrique.';
                        return;
                    }

                    statusEl.textContent = 'Cle biométrique enregistree. Lancez une verification.';
                    return;
                }

                const assertion = await navigator.credentials.get({
                    publicKey: {
                        challenge: fromBase64(authChallenge.challenge),
                        allowCredentials: [{
                            type: 'public-key',
                            id: fromBase64(authChallenge.credentialId)
                        }],
                        userVerification: 'required',
                        timeout: 60000
                    }
                });

                if (!assertion) {
                    statusEl.textContent = 'Biometrie annulee.';
                    return;
                }

                const complete = await postJson('biometric_verify.php?action=auth_complete', {
                    credentialId: assertion.id,
                    clientDataJSON: toBase64Url(new Uint8Array(assertion.response.clientDataJSON))
                });
                if (!complete.ok) {
                    statusEl.textContent = complete.error || 'Validation biométrique echouee.';
                    return;
                }

                statusEl.textContent = 'Biometrie validee. Redirection...';
                window.location.href = 'verify_2fa.php';
            } catch (error) {
                statusEl.textContent = 'Biometrie indisponible sur cet appareil/navigateur.';
            }
        });
    </script>
</body>
</html>

