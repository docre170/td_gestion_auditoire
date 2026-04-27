<?php

require_once 'auth.php';
require_once 'data_reader.php';
require_once 'planning_generator.php';
require_once 'planning_saver.php';
require_once 'planning_display.php';
require_once 'constraints.php';

exiger_authentification_complete();

// require_once sert à charger des pages ou simplement importé
$messages = [];
$loading_info = [];
$constraint_errors = [];

// --- 1. Chargement des données ---
$salles = charger_salles('data/salles.json');
$promotions = charger_promotions('data/promotions.json');
$cours = charger_cours('data/cours.json');
$options = charger_options('data/option.json');

$loading_info = [
    'Salles chargees' => count($salles),
    'Promotions chargees' => count($promotions),
    'Cours charges' => count($cours),
    'Options chargees' => count($options),
];

// Vérification si les données ont été chargées correctement
if (empty($salles) || empty($promotions) || empty($cours)) {
    $messages[] = ['type' => 'error', 'text' => "Impossible de charger toutes les donnees necessaires. Verifiez les fichiers JSON."];
    render_page($messages, '', $loading_info, [], $promotions, $options);
    exit;
}

$messages[] = ['type' => 'success', 'text' => "Donnees chargees avec succes."];

// --- Formulaire de saisie (ajout d'un cours) ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajouter_cours'])) {
    $identifiant = strtoupper(trim((string) ($_POST['identifiant'] ?? '')));
    $intitule = trim((string) ($_POST['intitule'] ?? ''));
    $volume = (int) ($_POST['volume_horaire'] ?? 0);
    $promotion_option = trim((string) ($_POST['promotion_option'] ?? ''));

    if ($identifiant === '' || $intitule === '' || $volume <= 0 || $promotion_option === '') {
        $messages[] = ['type' => 'error', 'text' => "Formulaire invalide: tous les champs du cours sont obligatoires."];
    } else {
        $id_existe = false;
        foreach ($cours as $cours_existant) {
            if (($cours_existant['identifiant'] ?? '') === $identifiant) {
                $id_existe = true;
                break;
            }
        }

        if ($id_existe) {
            $messages[] = ['type' => 'warning', 'text' => "Identifiant de cours deja utilise: {$identifiant}."];
        } else {
            $cours[] = [
                'identifiant' => $identifiant,
                'intitule' => $intitule,
                'volume_horaire' => $volume,
                'promotion_option' => $promotion_option,
            ];
            $messages[] = ['type' => 'success', 'text' => "Cours ajoute depuis le formulaire: {$intitule}."];
            $loading_info['Cours charges'] = count($cours);
        }
    }
}

// --- 2. Génération du planning ---
// Les créneaux fixes sont définis dans planning_generator.php
$planning = generer_planning($salles, $promotions, $cours, $options, $creneaux_fixes);

if (empty($planning)) {
    $messages[] = ['type' => 'warning', 'text' => "Aucun planning n'a pu etre genere. Verifiez les contraintes et les donnees."];
} else {
    $messages[] = ['type' => 'success', 'text' => "Planning genere avec succes."];
}

$constraint_errors = verifier_contraintes_planning($planning, $salles, $promotions, $options);
if (empty($constraint_errors)) {
    $messages[] = ['type' => 'success', 'text' => "Contraintes verifiees: aucun conflit detecte."];
} else {
    $messages[] = ['type' => 'warning', 'text' => "Contraintes verifiees: " . count($constraint_errors) . " probleme(s) detecte(s)."];
}

// --- 3. Sauvegarde du planning ---
$chemin_planning_json = 'output/planning.json';
$chemin_planning_txt = 'output/planning.txt';

// Créer le dossier 'output' s'il n'existe pas
if (!is_dir('output')) {
    mkdir('output', 0777, true);
}

if (sauvegarder_planning($planning, $chemin_planning_json)) {
    $messages[] = ['type' => 'success', 'text' => "Planning sauvegarde en JSON : {$chemin_planning_json}"];
} else {
    $messages[] = ['type' => 'error', 'text' => "Erreur lors de la sauvegarde du planning JSON."];
}

if (sauvegarder_planning_txt($planning, $chemin_planning_txt)) {
    $messages[] = ['type' => 'success', 'text' => "Planning sauvegarde en TXT : {$chemin_planning_txt}"];
} else {
    $messages[] = ['type' => 'error', 'text' => "Erreur lors de la sauvegarde du planning TXT."];
}

// --- 4. Affichage du planning ---
render_page(
    $messages,
    afficher_planning_html($planning, $salles, $cours),
    $loading_info,
    $constraint_errors,
    $promotions,
    $options
);

?>
<?php
function render_page(
    array $messages,
    string $planning_html,
    array $loading_info,
    array $constraint_errors,
    array $promotions,
    array $options
): void {
    ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning hebdomadaire</title>
    <link rel="stylesheet" href="./assets/css/style.css">
</head>

<body>
    <main class="page">
        <header class="page-header">
            <h1>Planning hebdomadaire</h1>
            <p>Inspire du modele officiel avec affichage dynamique des donnees.</p>
            <p><a href="logout.php">Se deconnecter</a></p>
        </header>

        <?php if (!empty($messages)): ?>
        <section class="messages">
            <?php foreach ($messages as $message): ?>
            <div class="message message-<?= htmlspecialchars($message['type'], ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars($message['text'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section class="planning-card">
            <h2>Chargement des donnees</h2>
            <div class="stats-grid">
                <?php foreach ($loading_info as $label => $value): ?>
                <div class="stat-item">
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <strong><?= (int) $value; ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="planning-card">
            <h2>Formulaire des saisies</h2>
            <form method="post" class="input-form">
                <div class="form-row">
                    <label for="identifiant">Identifiant</label>
                    <input id="identifiant" name="identifiant" type="text" required>
                </div>
                <div class="form-row">
                    <label for="intitule">Intitule du cours</label>
                    <input id="intitule" name="intitule" type="text" required>
                </div>
                <div class="form-row">
                    <label for="volume_horaire">Volume horaire</label>
                    <input id="volume_horaire" name="volume_horaire" type="number" min="1" required>
                </div>
                <div class="form-row">
                    <label for="promotion_option">Promotion / Option</label>
                    <select id="promotion_option" name="promotion_option" required>
                        <option value="">Selectionner...</option>
                        <?php foreach ($promotions as $promotion): ?>
                        <option value="<?= htmlspecialchars($promotion['identifiant'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($promotion['identifiant'] . ' - ' . $promotion['libelle'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php foreach ($options as $option): ?>
                        <option value="<?= htmlspecialchars($option['identifiant'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($option['identifiant'] . ' - ' . $option['libelle'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="ajouter_cours" value="1">Ajouter et regenerer</button>
            </form>
        </section>

        <section class="planning-card">
            <h2>Verification des contraintes</h2>
            <?php if (empty($constraint_errors)): ?>
            <!-- <p class="constraint-ok">Aucun conflit detecte apres verification.</p> -->
            <?php else: ?>
            <ul class="constraint-list">
                <?php foreach ($constraint_errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="planning-card">
            <h2>Resultat du planning</h2>
            <?= $planning_html; ?>
        </section>
    </main>
</body>

</html>
<?php
}