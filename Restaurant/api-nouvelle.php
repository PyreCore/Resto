<?php
/* Mini-API JSON pour les notifications de l'administration :
   - maxId        : plus grand id des commandes en cours (nouvelles commandes)
   - maxIdArchive : plus grand id des commandes archivées (nouvelles validations)
   - nbEnCours    : nombre de commandes en statut 'en_cours' (badge de la barre admin) */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    $bdd = connexion_bdd();
} catch (Exception $e) {
    echo json_encode(array('maxId' => 0, 'maxIdArchive' => 0, 'nbEnCours' => 0));
    exit;
}

echo json_encode(array(
    'maxId'        => (int)$bdd->query("SELECT COALESCE(MAX(id),0) FROM commandes")->fetchColumn(),
    'maxIdArchive' => (int)$bdd->query("SELECT COALESCE(MAX(id),0) FROM commandes_archivees")->fetchColumn(),
    'nbEnCours'    => (int)$bdd->query("SELECT COUNT(*) FROM commandes WHERE statut='en_cours'")->fetchColumn()
));
