<?php
/* ============================================
   scripts/sauvegarde.php - Sauvegarde de la base (LeDélise)
   Exporte toute la base « restaurant » (structure + données)
   dans un fichier backups/sauvegarde_AAAA-MM-JJ_HHMMSS.sql,
   puis supprime les sauvegardes plus anciennes que NB_JOURS.
   Exécution : en ligne de commande uniquement
     php scripts/sauvegarde.php   (ou double-clic sur sauvegarde.bat)
   ============================================ */

/* Réservé à la ligne de commande : refuser un appel via le navigateur */
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    die("Ce script doit être exécuté en ligne de commande.\n");
}

require_once __DIR__ . '/../config.php';

/* Durée de conservation des sauvegardes (en jours) */
define('NB_JOURS', 30);

$dossier = __DIR__ . '/../backups';
if (!is_dir($dossier)) {
    if (!mkdir($dossier, 0777, true)) {
        die("Impossible de créer le dossier backups/.\n");
    }
}

$nomFichier = 'sauvegarde_' . date('Y-m-d_H-i-s') . '.sql';
$chemin = $dossier . '/' . $nomFichier;

try {
    $bdd = connexion_bdd();
    $bdd->exec("SET NAMES utf8mb4");

    /* En-tête du fichier (restaurable tel quel, comme database.sql) */
    $sql = "-- ============================================\n"
         . "--  LeDélise - Sauvegarde de la base « " . BDD_NOM . " »\n"
         . "--  Date : " . date('Y-m-d H:i:s') . "\n"
         . "--  Restauration :  mysql -u " . BDD_UTILISATEUR . " < " . $nomFichier . "\n"
         . "-- ============================================\n\n"
         . "CREATE DATABASE IF NOT EXISTS `" . BDD_NOM . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
         . "USE `" . BDD_NOM . "`;\n"
         . "SET NAMES utf8mb4;\n\n";

    /* Structure et données de chaque table */
    $tables = $bdd->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $sql .= "-- --------------------------------------------\n"
              . "-- Table : " . $table . "\n"
              . "-- --------------------------------------------\n";
        $sql .= $bdd->query("SHOW CREATE TABLE `" . $table . "`")->fetch(PDO::FETCH_ASSOC)['Create Table'] . ";\n\n";

        $lignes = $bdd->query("SELECT * FROM `" . $table . "`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($lignes) === 0) {
            continue;
        }
        /* Colonnes entre accents graves */
        $colonnes = implode(', ', array_map(function ($c) {
            return '`' . $c . '`';
        }, array_keys($lignes[0])));

        /* Insertions groupées par lots de 100 lignes */
        foreach (array_chunk($lignes, 100) as $lot) {
            $valeurs = array_map(function ($ligne) use ($bdd) {
                return '(' . implode(', ', array_map(function ($v) use ($bdd) {
                    return $v === null ? 'NULL' : $bdd->quote((string)$v);
                }, $ligne)) . ')';
            }, $lot);
            $sql .= "INSERT INTO `" . $table . "` (" . $colonnes . ") VALUES\n  " . implode(",\n  ", $valeurs) . ";\n";
        }
        $sql .= "\n";
    }

    file_put_contents($chemin, $sql);

    /* Nettoyage des sauvegardes trop anciennes */
    $supprimes = 0;
    $limite = time() - NB_JOURS * 86400;
    foreach (glob($dossier . '/sauvegarde_*.sql') as $ancien) {
        if (filemtime($ancien) < $limite) {
            unlink($ancien);
            $supprimes++;
        }
    }

    $taille = number_format(filesize($chemin) / 1024, 1, ',', ' ');
    echo "Sauvegarde terminee : " . $nomFichier . "  (" . $taille . " Ko)\n";
    if ($supprimes > 0) {
        echo "Anciennes sauvegardes supprimees : " . $supprimes . "\n";
    }
} catch (Exception $e) {
    die("Echec de la sauvegarde : " . $e->getMessage() . "\n");
}
