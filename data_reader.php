<?php

/**
 * Charge le contenu d'un fichier JSON ou TXT en tableau associatif.
 * Gère les erreurs de fichier introuvable ou de format invalide.
 * @param string $chemin_fichier Le chemin vers le fichier.
 * @return array Le contenu du fichier sous forme de tableau associatif, ou un tableau vide en cas d'erreur.
 */
function charger_fichier_json_ou_txt(string $chemin_fichier): array {
    if (!file_exists($chemin_fichier)) {
        echo "Erreur: Le fichier '$chemin_fichier' est introuvable.\n";
        return [];
    }

    $contenu = file_get_contents($chemin_fichier);
    if ($contenu === false) {
        echo "Erreur: Impossible de lire le fichier '$chemin_fichier'.\n";
        return [];
    }

    // Tente de décoder comme JSON
    $data = json_decode($contenu, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $data;
    }

    // Si ce n'est pas du JSON valide, tente de lire comme TXT (CSV simple avec ';')
    // Cette partie est une hypothèse basée sur le TD qui mentionne JSON ou TXT.
    // Pour un format TXT plus complexe, il faudrait une logique de parsing plus élaborée.
    $lignes = explode("\n", $contenu);
    $data_txt = [];
    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);
        if (!empty($ligne)) {
            // Supposons un format clé;valeur ou des valeurs séparées par ';' pour les fichiers TXT
            // Pour l'exemple, nous allons juste stocker la ligne telle quelle ou la diviser si elle contient ';'
            $elements = explode(';', $ligne);
            if (count($elements) > 1) {
                $data_txt[] = $elements;
            } else {
                $data_txt[] = $ligne;
            }
        }
    }
    // Si le fichier TXT est censé représenter une liste d'objets, cette logique est insuffisante.
    // Le TD suggère des fichiers JSON/TXT pour les données structurées, donc JSON est privilégié.
    // Pour les besoins du TD, nous allons principalement nous concentrer sur JSON pour les données structurées.
    // Si le fichier n'est ni JSON, ni un TXT simple que nous pouvons parser facilement, nous retournons vide.
    if (!empty($data_txt) && is_array($data_txt[0])) { // Si on a réussi à diviser en éléments
        // On pourrait tenter de déduire les en-têtes si la première ligne est un en-tête
        // Pour l'instant, on retourne le tableau tel quel pour un TXT simple.
        return $data_txt;
    } else if (!empty($data_txt)) {
        // Si c'est un TXT avec une seule colonne ou des lignes non structurées
        return $data_txt;
    }

    echo "Erreur: Le fichier '$chemin_fichier' n'est ni un JSON valide, ni un format TXT simple supporté.\n";
    return [];
}

function charger_salles(string $chemin_fichier): array {
    return charger_fichier_json_ou_txt($chemin_fichier);
}

function charger_promotions(string $chemin_fichier): array {
    return charger_fichier_json_ou_txt($chemin_fichier);
}

function charger_cours(string $chemin_fichier): array {
    return charger_fichier_json_ou_txt($chemin_fichier);
}

function charger_options(string $chemin_fichier): array {
    return charger_fichier_json_ou_txt($chemin_fichier);
}

// Exemple d'utilisation (pour test)
// $salles = charger_salles('salles.json');
// print_r($salles);

?>
