<?php
include 'protection.php';

$pageCourante = 'historique';

/* Connexion à la base de données */
try {
    $bdd = connexion_bdd();
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: <br/>" . $e->getMessage());
}

/* Filtres de recherche */
$filtre = isset($_GET['filtre']) ? trim($_GET['filtre']) : '';
$filtreType = isset($_GET['type']) ? $_GET['type'] : '';

$where = array();
$params = array();
if ($filtre !== '') {
    $where[] = "(a.nom_client LIKE :f1 OR a.telephone LIKE :f2 OR CAST(a.id AS CHAR) LIKE :f3 OR CAST(COALESCE(a.commande_origine, a.id) AS CHAR) LIKE :f4 OR EXISTS (SELECT 1 FROM commandes_archivees_items ai WHERE ai.archive_id = a.id AND ai.menu LIKE :f5))";
    $params[':f1'] = '%' . $filtre . '%';
    $params[':f2'] = '%' . $filtre . '%';
    $params[':f3'] = '%' . $filtre . '%';
    $params[':f4'] = '%' . $filtre . '%';
    $params[':f5'] = '%' . $filtre . '%';
}
if ($filtreType !== '' && in_array($filtreType, array('sur_place', 'emporter'))) {
    $where[] = "a.type_commande = :type";
    $params[':type'] = $filtreType;
}
$sql = "SELECT a.* FROM commandes_archivees a";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY a.date_validation DESC, a.id DESC";

$reponse = $bdd->prepare($sql);
$reponse->execute($params);
$lignes = $reponse->fetchAll();
$reponse->closeCursor();

/* Tous les plats des commandes archivées, regroupés par commande */
$itemsParArchive = array();
$stmtItems = $bdd->query("SELECT * FROM commandes_archivees_items ORDER BY archive_id, id");
foreach ($stmtItems as $it) {
    $itemsParArchive[$it['archive_id']][] = $it;
}
$stmtItems->closeCursor();

/* Nombre de commandes en cours (notification d'information) */
$nbEnCours = (int)$bdd->query("SELECT COUNT(*) FROM commandes WHERE statut='en_cours'")->fetchColumn();

/* Récapitulatif du jour : commandes validées aujourd'hui + meilleure vente */
$stmtJour = $bdd->query("SELECT COUNT(*) AS nb, COALESCE(SUM(total),0) AS total FROM commandes_archivees WHERE DATE(date_validation) = CURDATE()");
$rJour = $stmtJour->fetch();
$stmtJour->closeCursor();
$nbAujourdhui = (int)$rJour['nb'];
$totalAujourdhui = (int)$rJour['total'];
$stmtBest = $bdd->query("SELECT ai.menu AS menu, SUM(ai.quantite) AS qte FROM commandes_archivees_items ai JOIN commandes_archivees a ON a.id = ai.archive_id WHERE DATE(a.date_validation) = CURDATE() GROUP BY ai.menu ORDER BY qte DESC, ai.menu ASC LIMIT 1");
$rBest = $stmtBest->fetch();
$stmtBest->closeCursor();
$meilleureVente = ($rBest !== false) ? $rBest['menu'] . ' (' . (int)$rBest['qte'] . ')' : '';

$bdd = null;

/* Regroupement des commandes par jour de validation + statistiques */
$jours = array();
$totalGeneral = 0;
$nbPlats = 0;
$clients = array();
foreach ($lignes as $l) {
    $cle = date('Y-m-d', strtotime($l['date_validation']));
    $jours[$cle][] = $l;
    $totalGeneral += (int)$l['total'];
    if (isset($itemsParArchive[$l['id']])) {
        foreach ($itemsParArchive[$l['id']] as $it) {
            $nbPlats += (int)$it['quantite'];
        }
    } else {
        $nbPlats++;
    }
    if ($l['nom_client'] !== null && $l['nom_client'] !== '') {
        $clients[$l['nom_client']] = true;
    }
}
$nbClients = count($clients);

/* Convertit une date SQL en libellé français complet */
function dateFr($dateSql) {
    $nomsJours = array('Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi');
    $nomsMois = array(1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
        7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre');
    $t = strtotime($dateSql);
    return $nomsJours[(int)date('w', $t)] . ' ' . date('j', $t) . ' ' . $nomsMois[(int)date('n', $t)] . ' ' . date('Y', $t);
}
include '../theme.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=25">
    <?php echo style_theme(); ?>
    <title>Historique - LeDélise</title>
</head>

<body class="page-admin">

<div class="fond"></div>

<?php include 'navbar.php'; ?>

<div class="container admin">

    <h1>Historique</h1>
    <p class="note">Commandes validées et archivées, triées par date.</p>

    <hr/>

    <!-- Récapitulatif du jour -->
    <div class="notification recap">
        <div>
            <strong>Aujourd'hui</strong>
            <span>
                <?php echo $nbAujourdhui; ?> commande(s) validée(s) · Total : <?php echo number_format($totalAujourdhui, 0, ',', ' '); ?> FCFA
                <?php if ($meilleureVente !== ''): ?> · Meilleure vente : <?php echo htmlspecialchars($meilleureVente); ?><?php endif; ?>
            </span>
        </div>
    </div>

    <?php if ($nbEnCours > 0): ?>
    <!-- Notification d'information : commandes en cours à traiter -->
    <div class="notification alerte">
        <div>
            <strong><?php echo $nbEnCours; ?> commande(s) en cours</strong>
            <span>à traiter dans l'administration — <a href="index.php">Voir les commandes</a></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notification de nouvelle commande validée (masquée par défaut) -->
    <div id="notification" class="notification" style="display:none;">
        <div>
            <strong id="notification-texte">Nouvelle commande validée !</strong>
            <span>La liste va être actualisée...</span>
        </div>
    </div>

    <?php if (count($lignes) > 0): ?>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat">
            <strong><?php echo count($lignes); ?></strong>
            <span>Commande(s) archivées</span>
        </div>
        <div class="stat">
            <strong><?php echo number_format($totalGeneral, 0, ',', ' '); ?> F</strong>
            <span>Total ventes</span>
        </div>
        <div class="stat">
            <strong><?php echo $nbClients; ?></strong>
            <span>Client(s)</span>
        </div>
        <div class="stat">
            <strong><?php echo count($jours); ?></strong>
            <span>Jour(s)</span>
        </div>
    </div>

    <?php endif; ?>

    <!-- Filtres de recherche -->
    <form method="get" action="historique.php" class="filtres">
        <input type="text" name="filtre" placeholder="Rechercher (client, téléphone, plat, numéro...)" value="<?php echo htmlspecialchars($filtre); ?>"/>
        <select name="type" onchange="this.form.submit()">
            <option value="">Tous les types</option>
            <option value="sur_place"<?php echo ($filtreType === 'sur_place') ? ' selected' : ''; ?>>Sur place</option>
            <option value="emporter"<?php echo ($filtreType === 'emporter') ? ' selected' : ''; ?>>Emporter</option>
        </select>
        <button type="submit">Filtrer</button>
        <?php if ($filtre !== '' || $filtreType !== ''): ?>
        <a class="btn-action reset" href="historique.php">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <?php if (count($lignes) === 0): ?>

    <div class="erreur">Aucune commande archivée pour le moment. Validez une commande pour l'archiver.</div>

    <?php else: ?>

    <!-- Commandes groupées par jour -->
    <?php foreach ($jours as $cleJour => $items): ?>
    <?php
        $totalJour = 0;
        foreach ($items as $l) {
            $totalJour += (int)$l['total'];
        }
    ?>
    <div class="groupe-jour">
        <div class="groupe-jour-tete">
            <h2><?php echo dateFr($cleJour); ?></h2>
            <span><?php echo count($items); ?> commande(s) · <?php echo number_format($totalJour, 0, ',', ' '); ?> FCFA</span>
        </div>

        <?php foreach ($items as $ligne): ?>
        <?php
            $typeBadge = ($ligne['type_commande'] === 'sur_place') ? 'badge-surplace' : 'badge-emporter';
            $typeLibelle = ($ligne['type_commande'] === 'sur_place') ? 'Sur place' : 'Emporter';
            $heure = date('H:i', strtotime($ligne['date_validation']));
            $table = ($ligne['numero_table'] !== null) ? 'Table n° ' . $ligne['numero_table'] : '';
            $client = ($ligne['nom_client'] !== null && $ligne['nom_client'] !== '') ? $ligne['nom_client'] : '—';
            $telephone = ($ligne['telephone'] !== null && $ligne['telephone'] !== '') ? $ligne['telephone'] : '—';
            $numeroCommande = ($ligne['commande_origine'] !== null) ? (int)$ligne['commande_origine'] : (int)$ligne['id'];
            $itemsCommande = isset($itemsParArchive[$ligne['id']]) ? $itemsParArchive[$ligne['id']] : array();
        ?>
        <div class="carte">
            <div class="carte-tete">
                <span class="carte-ticket">Commande n° <?php echo $numeroCommande; ?></span>
                <span class="badge <?php echo $typeBadge; ?>"><?php echo $typeLibelle; ?></span>
                <span class="badge-statut livree">Validée</span>
            </div>
            <div class="carte-infos">
                <span class="sous-infos"><?php echo htmlspecialchars($client);
                    if ($table !== '') {
                        echo ' · ' . htmlspecialchars($table);
                    }
                    if ($telephone !== '—') {
                        echo ' · ' . htmlspecialchars($telephone);
                    }
                ?></span>
            </div>
            <ul class="carte-plats">
                <?php foreach ($itemsCommande as $item): ?>
                <?php $qte = ($item['quantite'] > 1) ? ' × ' . $item['quantite'] : ''; ?>
                <li><strong><?php echo htmlspecialchars($item['menu']); ?><?php echo $qte; ?></strong> — <?php echo number_format($item['prix'] * $item['quantite'], 0, ',', ' '); ?> FCFA</li>
                <?php endforeach; ?>
            </ul>
            <div class="carte-date">Validée à <?php echo $heure; ?></div>
            <div class="carte-total">Total : <?php echo number_format($ligne['total'], 0, ',', ' '); ?> FCFA</div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php include '../footer.php'; ?>

<script>
    /* Détection des nouvelles commandes validées (archivées ailleurs dans l'admin) :
       interrogation de l'API toutes les 8 secondes, comparaison avec le plus grand id
       d'archive déjà vu (localStorage) */
    var cleStockageArchive = 'restaurant_admin_max_archive_vu';
    var maxIdArchiveVu = parseInt(localStorage.getItem(cleStockageArchive), 10) || 0;
    var notification = document.getElementById('notification');
    var notificationAffichee = false;

    function verifierNouvellesArchives() {
        fetch('../api-nouvelle.php')
            .then(function (reponse) { return reponse.json(); })
            .then(function (donnees) {
                if (donnees.maxIdArchive > maxIdArchiveVu) {
                    maxIdArchiveVu = donnees.maxIdArchive;
                    localStorage.setItem(cleStockageArchive, maxIdArchiveVu);
                    if (!notificationAffichee) {
                        notificationAffichee = true;
                        notification.style.display = 'flex';
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                }
            })
            .catch(function () {});
    }

    /* Synchronisation initiale : les archives déjà présentes ne déclenchent pas de notification */
    fetch('../api-nouvelle.php')
        .then(function (reponse) { return reponse.json(); })
        .then(function (donnees) {
            if (donnees.maxIdArchive > maxIdArchiveVu) {
                maxIdArchiveVu = donnees.maxIdArchive;
                localStorage.setItem(cleStockageArchive, maxIdArchiveVu);
            }
        })
        .catch(function () {});

    setInterval(verifierNouvellesArchives, 8000);
</script>

</body>

</html>
