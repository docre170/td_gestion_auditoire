<?php

/**
 * Sauvegarde le planning dans un fichier au format JSON.
 * @param array $planning Le planning à sauvegarder.
 * @param string $chemin_fichier Le chemin du fichier de destination.
 * @return bool True en cas de succès, False en cas d'échec.
 */
function sauvegarder_planning(array $planning, string $chemin_fichier): bool {
    $json_content = json_encode($planning, JSON_PRETTY_PRINT);
    if ($json_content === false) {
        echo "Erreur lors de l'encodage du planning en JSON.\n";
        return false;
    }

    if (file_put_contents($chemin_fichier, $json_content) === false) {
        echo "Erreur lors de l'écriture du fichier ".$chemin_fichier.".\n";
        return false;
    }
    return true;
}

/**
 * Sauvegarde le planning dans un fichier au format TXT.
 * Le format TXT sera une ligne par affectation, avec les champs séparés par des points-virgules.
 * La première ligne sera l'en-tête.
 * @param array $planning Le planning à sauvegarder.
 * @param string $chemin_fichier Le chemin du fichier de destination.
 * @return bool True en cas de succès, False en cas d'échec.
 */
function sauvegarder_planning_txt(array $planning, string $chemin_fichier): bool {
    if (empty($planning)) {
        file_put_contents($chemin_fichier, "");
        return true;
    }

    $lignes = [];
    // En-tête
    $lignes[] = implode(';', array_keys($planning[0]));

    foreach ($planning as $affectation) {
        $lignes[] = implode(';', array_values($affectation));
    }

    $txt_content = implode("\n", $lignes);

    if (file_put_contents($chemin_fichier, $txt_content) === false) {
        echo "Erreur lors de l'écriture du fichier ".$chemin_fichier.".\n";
        return false;
    }
    return true;
}

?>
