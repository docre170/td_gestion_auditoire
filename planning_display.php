<?php

require_once 'data_reader.php'; // Pour charger_fichier_json_ou_txt

/**
 * Charge le planning depuis un fichier JSON.
 * @param string $chemin_fichier Le chemin vers le fichier planning.json.
 * @return array Le planning sous forme de tableau associatif, ou un tableau vide en cas d'erreur.
 */
function charger_planning(string $chemin_fichier): array {
    return charger_fichier_json_ou_txt($chemin_fichier);
}

/**
 * Affiche le planning hebdomadaire sous forme de tableau HTML.
 * @param array $planning Le planning à afficher.
 * @param array $salles Le tableau des salles pour afficher les désignations.
 * @param array $cours Le tableau des cours pour afficher les intitulés.
 * @return string Le code HTML du tableau de planning.
 */
function afficher_planning_html(array $planning, array $salles, array $cours): string {
    $html = '<table class="planning-table">';
    $html .= '<thead><tr><th>Jour</th><th>Heure debut</th><th>Heure fin</th><th>Salle</th><th>Cours</th><th>Groupe</th></tr></thead>';
    $html .= '<tbody>';

    $jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $salles_par_id = [];
    foreach ($salles as $salle) {
        $salles_par_id[$salle['identifiant']] = $salle['designation'];
    }

    $cours_par_id = [];
    foreach ($cours as $un_cours) {
        $cours_par_id[$un_cours['identifiant']] = $un_cours['intitule'];
    }

    foreach ($jours_semaine as $jour) {
        $affectations_du_jour = array_filter($planning, function($affectation) use ($jour) {
            return $affectation['jour'] === $jour;
        });

        // Trier les affectations du jour par heure de début
        usort($affectations_du_jour, function($a, $b) {
            return strtotime($a['heure_debut']) <=> strtotime($b['heure_debut']);
        });

        if (empty($affectations_du_jour)) {
            $html .= '<tr><td class="planning-day">' . htmlspecialchars($jour, ENT_QUOTES, 'UTF-8') . '</td><td colspan="5" class="planning-empty">Aucune affectation</td></tr>';
        } else {
            $first_row_for_day = true;
            foreach ($affectations_du_jour as $affectation) {
                $nom_salle = $salles_par_id[$affectation['id_salle']] ?? $affectation['id_salle'];
                $intitule_cours = $cours_par_id[$affectation['id_cours']] ?? $affectation['id_cours'];

                $html .= '<tr>';
                if ($first_row_for_day) {
                    $html .= '<td rowspan="' . count($affectations_du_jour) . '" class="planning-day">' . htmlspecialchars($jour, ENT_QUOTES, 'UTF-8') . '</td>';
                    $first_row_for_day = false;
                }
                $html .= '<td>' . htmlspecialchars($affectation['heure_debut'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($affectation['heure_fin'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td>' . htmlspecialchars($nom_salle, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($affectation['id_salle'], ENT_QUOTES, 'UTF-8') . ')</td>';
                $html .= '<td>' . htmlspecialchars($intitule_cours, ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($affectation['id_cours'], ENT_QUOTES, 'UTF-8') . ')</td>';
                $html .= '<td>' . htmlspecialchars($affectation['id_groupe'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '</tr>';
            }
        }
    }

    $html .= '</tbody>';
    $html .= '</table>';
    return $html;
}

?>
