<?php

/**
 * Vérifie si une salle est disponible pour un créneau donné.
 * Une salle est disponible si aucun autre cours n'y est affecté pendant ce créneau.
 * @param array $planning Le tableau du planning actuel.
 * @param string $id_salle L'identifiant de la salle à vérifier.
 * @param array $creneau Le créneau horaire à vérifier (ex: ['jour' => 'Lundi', 'heure_debut' => '08h00', 'heure_fin' => '12h00']).
 * @return bool True si la salle est disponible, False sinon.
 */
function salle_disponible(array $planning, string $id_salle, array $creneau): bool {
    foreach ($planning as $affectation) {
        if ($affectation['id_salle'] === $id_salle && $affectation['jour'] === $creneau['jour']) {
            // Vérifier le chevauchement des créneaux
            // Un chevauchement existe si (start1 < end2) et (end1 > start2)
            $start1 = strtotime($affectation['heure_debut']);
            $end1 = strtotime($affectation['heure_fin']);
            $start2 = strtotime($creneau['heure_debut']);
            $end2 = strtotime($creneau['heure_fin']);

            if ($start1 < $end2 && $end1 > $start2) {
                return false; // Chevauchement trouvé, salle non disponible
            }
        }
    }
    return true; // Aucune affectation ne chevauche, salle disponible
}

/**
 * Vérifie si la capacité d'une salle est suffisante pour un effectif donné.
 * @param array $salles Le tableau des salles.
 * @param string $id_salle L'identifiant de la salle.
 * @param int $effectif L'effectif à accueillir.
 * @return bool True si la capacité est suffisante, False sinon.
 */
function capacite_suffisante(array $salles, string $id_salle, int $effectif): bool {
    foreach ($salles as $salle) {
        if ($salle['identifiant'] === $id_salle) {
            return $salle['capacite'] >= $effectif;
        }
    }
    return false; // Salle non trouvée, ou capacité insuffisante par défaut
}

/**
 * Vérifie si un groupe est libre pour un créneau donné.
 * Un groupe est libre si aucun de ses cours n'est déjà affecté pendant ce créneau.
 * @param array $planning Le tableau du planning actuel.
 * @param string $id_groupe L'identifiant du groupe (ou promotion) à vérifier.
 * @param array $creneau Le créneau horaire à vérifier (ex: ['jour' => 'Lundi', 'heure_debut' => '08h00', 'heure_fin' => '12h00']).
 * @return bool True si le groupe est libre, False sinon.
 */
function creneau_libre_groupe(array $planning, string $id_groupe, array $creneau): bool {
    foreach ($planning as $affectation) {
        // On suppose que id_groupe dans le planning peut être l'id de la promotion ou de l'option
        // et que le TD implique que chaque affectation est liée à un groupe/promotion.
        // Pour simplifier, on vérifie si l'id_groupe de l'affectation correspond à celui qu'on cherche.
        if ($affectation['id_groupe'] === $id_groupe && $affectation['jour'] === $creneau['jour']) {
            $start1 = strtotime($affectation['heure_debut']);
            $end1 = strtotime($affectation['heure_fin']);
            $start2 = strtotime($creneau['heure_debut']);
            $end2 = strtotime($creneau['heure_fin']);

            if ($start1 < $end2 && $end1 > $start2) {
                return false; // Chevauchement trouvé, groupe non libre
            }
        }
    }
    return true; // Aucune affectation ne chevauche, groupe libre
}

/**
 * Verifie les contraintes globales d'un planning deja genere.
 * Retourne la liste des anomalies detectees.
 *
 * @param array $planning
 * @param array $salles
 * @param array $promotions
 * @param array $options
 * @return array
 */
function verifier_contraintes_planning(array $planning, array $salles, array $promotions, array $options): array {
    $erreurs = [];

    $capacites = [];
    foreach ($salles as $salle) {
        $capacites[$salle['identifiant']] = (int) ($salle['capacite'] ?? 0);
    }

    $effectifs_groupes = [];
    foreach ($promotions as $promotion) {
        $effectifs_groupes[$promotion['identifiant']] = (int) ($promotion['effectif_total'] ?? 0);
    }
    foreach ($options as $option) {
        $effectifs_groupes[$option['identifiant']] = (int) ($option['effectif'] ?? 0);
    }

    $total = count($planning);
    for ($i = 0; $i < $total; $i++) {
        $a = $planning[$i];

        $salle = $a['id_salle'] ?? '';
        $groupe = $a['id_groupe'] ?? '';
        $jour = $a['jour'] ?? '';
        $debut = $a['heure_debut'] ?? '';
        $fin = $a['heure_fin'] ?? '';

        if (!isset($capacites[$salle])) {
            $erreurs[] = "Salle inconnue detectee: {$salle}.";
        } else {
            $effectif = $effectifs_groupes[$groupe] ?? 0;
            if ($effectif > 0 && $capacites[$salle] < $effectif) {
                $erreurs[] = "Capacite insuffisante: {$salle} ({$capacites[$salle]}) pour {$groupe} ({$effectif}).";
            }
        }

        for ($j = $i + 1; $j < $total; $j++) {
            $b = $planning[$j];
            if (($b['jour'] ?? '') !== $jour) {
                continue;
            }

            $aStart = strtotime($debut);
            $aEnd = strtotime($fin);
            $bStart = strtotime($b['heure_debut'] ?? '');
            $bEnd = strtotime($b['heure_fin'] ?? '');
            $overlap = ($aStart < $bEnd && $aEnd > $bStart);

            if (!$overlap) {
                continue;
            }

            if (($b['id_salle'] ?? '') === $salle) {
                $erreurs[] = "Conflit salle: {$salle} utilisee deux fois {$jour} sur le meme creneau.";
            }
            if (($b['id_groupe'] ?? '') === $groupe) {
                $erreurs[] = "Conflit groupe: {$groupe} planifie deux fois {$jour} sur le meme creneau.";
            }
        }
    }

    return array_values(array_unique($erreurs));
}

?>
