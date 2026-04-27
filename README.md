# td_gestion_auditoire
td de la fac
-------------------------------------------------------------------------------------------------------------------------
1. MISE EN SITUATION

La Faculte des Sciences Informatiques voit ses effectifs croitre d’annee en annee,
rendant la gestion manuelle des salles et des horaires de plus en plus laborieuse
et source d’erreurs repetees. L’apparitorat facultaire — le service administratif
responsable de la logistique pedagogique — se retrouve regulierement confronte a
des situations problematiques : deux promotions affectees au meme auditoire sur le
meme creneau, des salles sous-exploitees pendant que d’autres debordent, ou encore
des cours d’option planifies sans respecter les contraintes de capacite des espaces
disponibles.
Face a cette situation, l’apparitorat souhaite se doter d’un Systeme de Gestion des
Auditoires (SGA), une application web developpee en PHP procedural capable
de :
— repertorier les 6 salles disponibles avec leurs capacites respectives ;
— enregistrer les 4 promotions (L1, L2, L3, L4), leurs effectifs et leurs grilles de
cours ;
— distinguer les cours de tronc commun (suivis par toute une promotion) des
cours d’option specifiques aux L3 et L4 ;
— proposer automatiquement une repartition hebdomadaire des creneaux horaires, en affectant chaque cours a une salle adaptee, sans collision ni depassement
de capacite ;
— sauvegarder toutes les donnees et le planning genere dans des fichiers (.json
ou .txt), et permettre leur rechargement ulterieu