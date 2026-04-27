<?php

require_once 'constraints.php';

/**
 * Génère un planning hebdomadaire sans conflit.
 * @param array $salles Tableau des salles.
 * @param array $promotions Tableau des promotions.
 * @param array $cours Tableau des cours.
 * @param array $options Tableau des options.
 * @param array $creneaux_disponibles Tableau des créneaux horaires disponibles.
 * @return array Le planning généré sous forme de tableau d'affectations.
 */
function generer_planning(array $salles, array $promotions, array $cours, array $options, array $creneaux_disponibles): array {
    $planning = [];
    $jours_semaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    // Horaire souhaite: cours -> jour + creneau fixe
    $affectations_souhaitees = [
        'TMO201' => ['jour' => 'Lundi', 'heure_debut' => '08h00', 'heure_fin' => '12h00'],
        'RES202' => ['jour' => 'Lundi', 'heure_debut' => '13h00', 'heure_fin' => '17h00'],
        'MAN203' => ['jour' => 'Mardi', 'heure_debut' => '08h00', 'heure_fin' => '12h00'],
        'IRS204' => ['jour' => 'Mardi', 'heure_debut' => '13h00', 'heure_fin' => '17h00'],
        'PSE205' => ['jour' => 'Mercredi', 'heure_debut' => '08h00', 'heure_fin' => '12h00'],
        'DRI206' => ['jour' => 'Mercredi', 'heure_debut' => '13h00', 'heure_fin' => '17h00'],
        'GPI207' => ['jour' => 'Jeudi', 'heure_debut' => '08h00', 'heure_fin' => '12h00'],
        'ANG208' => ['jour' => 'Jeudi', 'heure_debut' => '13h00', 'heure_fin' => '17h00'],
        'PHP209A' => ['jour' => 'Vendredi', 'heure_debut' => '08h00', 'heure_fin' => '12h00'],
        'PHP209B' => ['jour' => 'Vendredi', 'heure_debut' => '13h00', 'heure_fin' => '17h00'],
        'PRJ210' => ['jour' => 'Samedi', 'heure_debut' => '08h00', 'heure_fin' => '12h00'],
    ];

    // Trier les cours par volume horaire décroissant pour essayer d'affecter les plus longs en premier
    usort($cours, function($a, $b) {
        return $b['volume_horaire'] <=> $a['volume_horaire'];
    });

    foreach ($cours as $un_cours) {
        $cours_affecte = false;
        // Déterminer l'effectif du groupe/promotion pour ce cours
        $effectif_cours = 0;
        if (isset($un_cours['promotion_option'])) {
            foreach ($promotions as $promo) {
                if ($promo['identifiant'] === $un_cours['promotion_option']) {
                    $effectif_cours = $promo['effectif_total'];
                    break;
                }
            }
            if ($effectif_cours === 0) { // Si ce n'est pas une promotion, c'est peut-être une option
                foreach ($options as $option) {
                    if ($option['identifiant'] === $un_cours['promotion_option']) {
                        $effectif_cours = $option['effectif'];
                        break;
                    }
                }
            }
        }

        // Si l'effectif n'a pas été trouvé, on ne peut pas planifier ce cours
        if ($effectif_cours === 0) {
            echo "Avertissement: Effectif non trouvé pour le cours {$un_cours['identifiant']}. Il ne sera pas planifié.\n";
            continue;
        }

        // Si un creneau fixe existe pour ce cours, on force l'affectation sur ce creneau
        if (isset($affectations_souhaitees[$un_cours['identifiant']])) {
            $creneau = $affectations_souhaitees[$un_cours['identifiant']];
            foreach ($salles as $salle) {
                if (salle_disponible($planning, $salle['identifiant'], $creneau) &&
                    capacite_suffisante($salles, $salle['identifiant'], $effectif_cours) &&
                    creneau_libre_groupe($planning, $un_cours['promotion_option'], $creneau)) {
                    $planning[] = [
                        'id_salle' => $salle['identifiant'],
                        'id_cours' => $un_cours['identifiant'],
                        'id_groupe' => $un_cours['promotion_option'],
                        'jour' => $creneau['jour'],
                        'heure_debut' => $creneau['heure_debut'],
                        'heure_fin' => $creneau['heure_fin']
                    ];
                    $cours_affecte = true;
                    break;
                }
            }
        } else {
            foreach ($jours_semaine as $jour) {
                foreach ($creneaux_disponibles as $creneau_base) {
                    $creneau = [
                        'jour' => $jour,
                        'heure_debut' => $creneau_base['heure_debut'],
                        'heure_fin' => $creneau_base['heure_fin']
                    ];

                    foreach ($salles as $salle) {
                        // Vérifier toutes les contraintes avant d'affecter
                        if (salle_disponible($planning, $salle['identifiant'], $creneau) &&
                            capacite_suffisante($salles, $salle['identifiant'], $effectif_cours) &&
                            creneau_libre_groupe($planning, $un_cours['promotion_option'], $creneau)) {

                            $planning[] = [
                                'id_salle' => $salle['identifiant'],
                                'id_cours' => $un_cours['identifiant'],
                                'id_groupe' => $un_cours['promotion_option'], // Utiliser la promotion/option comme identifiant de groupe
                                'jour' => $creneau['jour'],
                                'heure_debut' => $creneau['heure_debut'],
                                'heure_fin' => $creneau['heure_fin']
                            ];
                            $cours_affecte = true;
                            break 3; // Sortir des boucles salle, créneau et jour
                        }
                    }
                }
            }
        }
        if (!$cours_affecte) {
            echo "Avertissement: Le cours {$un_cours['identifiant']} n'a pas pu être affecté.\n";
        }
    }

    return $planning;
}

// Créneaux horaires de 4 heures entre 8h00 et 17h00
$creneaux_fixes = [
    ['heure_debut' => '08h00', 'heure_fin' => '12h00'],
    ['heure_debut' => '13h00', 'heure_fin' => '17h00']
];

?>
