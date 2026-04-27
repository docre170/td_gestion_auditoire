<?php
require_once 'auth.php';

demarrer_session_securisee();

if (!empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!utilisateur_autorise($username, $password)) {
        $error = "Identifiants invalides.";
    } else {
        $_SESSION['pending_user'] = $username;
        $_SESSION['otp_code'] = generer_otp();
        $_SESSION['otp_expires_at'] = time() + 300;
        $_SESSION['otp_verified'] = false;
        $_SESSION['biometric_verified'] = false;
        header('Location: verify_2fa.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion securisee</title>
    <link rel="stylesheet" href="./assets/css/auth.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card">
            <h1>Connexion</h1>
            <p>Acces protege: mot de passe + double authentification.</p>
            <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <label for="username">Utilisateur</label>
                <input id="username" name="username" type="text" required>
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" required>
                <button type="submit">Continuer</button>
            </form>
            <small class="hint">Compte de demo: admin / upc123</small>
        </section>
    </main>
</body>

</html>