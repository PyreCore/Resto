<?php
include 'protection.php';

$pageCourante = 'admin';

/* Connexion à la base de données */
try {
    $bdd = connexion_bdd();
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: <br/>" . $e->getMessage());
}

$message = '';

/* Validation d'une commande : elle quitte la liste globale et est archivée
   (en-tête dans commandes_archivees + une ligne par plat). Action via lien
   GET : le jeton CSRF est requis. */
if (isset($_GET['action']) && $_GET['action'] === 'valider'
    && isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!csrf_valider()) {
        $message = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
    } else {
    $id = (int)$_GET['id'];
    $stmt = $bdd->prepare("SELECT * FROM commandes WHERE id=:id");
    $stmt->execute(array(':id' => $id));
    $commande = $stmt->fetch();
    $stmt->closeCursor();
    if ($commande) {
        $stmtItems = $bdd->prepare("SELECT * FROM commandes_items WHERE commande_id=:commande_id");
        $stmtItems->execute(array(':commande_id' => $id));
        $itemsCommande = $stmtItems->fetchAll();
        $stmtItems->closeCursor();

        $bdd->beginTransaction();
        /* L'archive reçoit un id interne auto-incrémenté ; le numéro de la commande d'origine
           est conservé dans commande_origine (évite les conflits de clé primaire si les ids
           de commandes sont réutilisés) */
        $bdd->prepare("INSERT INTO commandes_archivees (type_commande, numero_table, nom_client, telephone, date, total, commande_origine) VALUES (:type_commande, :numero_table, :nom_client, :telephone, :date, :total, :commande_origine)")
            ->execute(array(
                ':type_commande' => $commande['type_commande'],
                ':numero_table' => $commande['numero_table'],
                ':nom_client' => $commande['nom_client'],
                ':telephone' => $commande['telephone'],
                ':date' => $commande['date'],
                ':total' => (int)$commande['total'],
                ':commande_origine' => (int)$commande['id']
            ));
        $archiveId = (int)$bdd->lastInsertId();
        /* Copie des plats de la commande vers l'archive */
        $stmtArchiveItems = $bdd->prepare("INSERT INTO commandes_archivees_items (archive_id, menu, prix, quantite) VALUES (:archive_id, :menu, :prix, :quantite)");
        foreach ($itemsCommande as $item) {
            $stmtArchiveItems->execute(array(
                ':archive_id' => $archiveId,
                ':menu' => $item['menu'],
                ':prix' => (int)$item['prix'],
                ':quantite' => (int)$item['quantite']
            ));
        }
        $bdd->prepare("DELETE FROM commandes_items WHERE commande_id=:commande_id")
            ->execute(array(':commande_id' => $id));
        $bdd->prepare("DELETE FROM commandes WHERE id=:id")
            ->execute(array(':id' => $id));
        $bdd->commit();
        $message = '<div class="succes">La commande n° ' . $id . ' a été validée et archivée dans l\'historique.</div>';
    }
    }
}

/* Changement de statut d'une commande (ligne individuelle, jeton CSRF requis) */
$statuts = array('en_cours', 'livree', 'annulee');
if (isset($_GET['action']) && $_GET['action'] === 'statut'
    && isset($_GET['id']) && is_numeric($_GET['id'])
    && isset($_GET['nouveau_statut']) && in_array($_GET['nouveau_statut'], $statuts)) {
    if (!csrf_valider()) {
        $message = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
    } else {
        $id = (int)$_GET['id'];
        $bdd->prepare("UPDATE commandes SET statut=:statut WHERE id=:id")
            ->execute(array(':statut' => $_GET['nouveau_statut'], ':id' => $id));
        $message = '<div class="succes">Le statut de la commande a bien été modifié.</div>';
    }
}

/* Filtres de recherche */
$filtre = isset($_GET['filtre']) ? trim($_GET['filtre']) : '';
$filtreStatut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filtreType = isset($_GET['type']) ? $_GET['type'] : '';
$filtreDate = isset($_GET['date']) ? trim($_GET['date']) : '';

$where = array();
$params = array();
if ($filtre !== '') {
    $where[] = "(c.nom_client LIKE :f1 OR c.telephone LIKE :f2 OR CAST(c.id AS CHAR) LIKE :f3 OR EXISTS (SELECT 1 FROM commandes_items ci WHERE ci.commande_id = c.id AND ci.menu LIKE :f4))";
    $params[':f1'] = '%' . $filtre . '%';
    $params[':f2'] = '%' . $filtre . '%';
    $params[':f3'] = '%' . $filtre . '%';
    $params[':f4'] = '%' . $filtre . '%';
}
if ($filtreStatut !== '' && in_array($filtreStatut, $statuts)) {
    $where[] = "c.statut = :statut";
    $params[':statut'] = $filtreStatut;
}
if ($filtreType !== '' && in_array($filtreType, array('sur_place', 'emporter'))) {
    $where[] = "c.type_commande = :type";
    $params[':type'] = $filtreType;
}
if ($filtreDate !== '') {
    $where[] = "DATE(c.date) = :date";
    $params[':date'] = $filtreDate;
}
$sql = "SELECT c.* FROM commandes c";
if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY c.id DESC";

$reponse = $bdd->prepare($sql);
$reponse->execute($params);
$lignes = $reponse->fetchAll();
$reponse->closeCursor();

/* Tous les plats des commandes, regroupés par commande */
$itemsParCommande = array();
$stmtItems = $bdd->query("SELECT * FROM commandes_items ORDER BY commande_id, id");
foreach ($stmtItems as $it) {
    $itemsParCommande[$it['commande_id']][] = $it;
}
$stmtItems->closeCursor();

/* Statistiques (sur le résultat filtré) */
$totalGeneral = 0;
$nbPlats = 0;
$clients = array();

foreach ($lignes as $l) {
    $totalGeneral += (int)$l['total'];
    if (isset($itemsParCommande[$l['id']])) {
        foreach ($itemsParCommande[$l['id']] as $it) {
            $nbPlats += (int)$it['quantite'];
        }
    } else {
        $nbPlats++;
    }
    if ($l['nom_client'] !== null && $l['nom_client'] !== '') {
        $clients[$l['nom_client']] = true;
    }
}
$nbCommandes = count($lignes);
$nbClients = count($clients);

$libelleStatut = array(
    'en_cours' => 'En cours',
    'livree' => 'Livrée',
    'annulee' => 'Annulée'
);

function classeStatut($statut) {
    $map = array('en_cours' => 'en-cours', 'livree' => 'livree', 'annulee' => 'annulee');
    return isset($map[$statut]) ? $map[$statut] : '';
}
?>
<?php include '../theme.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=25">
    <?php echo style_theme(); ?>
    <title>Administration - LeDélise</title>
</head>

<body class="page-admin">

<div class="fond"></div>

<?php include 'navbar.php'; ?>

<div class="container admin">

    <h1>Administration</h1>
    <p class="note">Gestion des commandes du restaurant</p>

    <hr/>

    <?php echo $message; ?>

    <?php if ($nbCommandes > 0): ?>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat">
            <strong><?php echo $nbCommandes; ?></strong>
            <span>Commande(s)</span>
        </div>
        <div class="stat">
            <strong><?php echo number_format($totalGeneral, 0, ',', ' '); ?> F</strong>
            <span>Total ventes</span>
        </div>
        <div class="stat">
            <strong><?php echo $nbPlats; ?></strong>
            <span>Plat(s)</span>
        </div>
        <div class="stat">
            <strong><?php echo $nbClients; ?></strong>
            <span>Client(s)</span>
        </div>
    </div>

    <?php endif; ?>

    <!-- Filtres de recherche -->
    <form method="get" action="index.php" class="filtres">
        <input type="text" name="filtre" placeholder="Rechercher (client, téléphone, plat, numéro...)" value="<?php echo htmlspecialchars($filtre); ?>"/>
        <select name="statut" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            <?php foreach ($libelleStatut as $val => $lab): ?>
            <option value="<?php echo $val; ?>"<?php echo ($filtreStatut === $val) ? ' selected' : ''; ?>><?php echo $lab; ?></option>
            <?php endforeach; ?>
        </select>
        <select name="type" onchange="this.form.submit()">
            <option value="">Tous les types</option>
            <option value="sur_place"<?php echo ($filtreType === 'sur_place') ? ' selected' : ''; ?>>Sur place</option>
            <option value="emporter"<?php echo ($filtreType === 'emporter') ? ' selected' : ''; ?>>Emporter</option>
        </select>
        <input type="date" name="date" value="<?php echo htmlspecialchars($filtreDate); ?>"/>
        <button type="submit">Filtrer</button>
        <?php if ($filtre !== '' || $filtreStatut !== '' || $filtreType !== '' || $filtreDate !== ''): ?>
        <a class="btn-action reset" href="index.php">Réinitialiser</a>
        <?php endif; ?>
    </form>

    <!-- Notification de nouvelle commande (masquée par défaut) -->
    <div id="notification" class="notification" style="display:none;">
        <div>
            <strong id="notification-texte">Nouvelle commande reçue !</strong>
            <span>La liste va être actualisée...</span>
        </div>
    </div>

    <?php
    /* Affichage des commandes sous forme de cartes (une carte par commande,
       avec la liste des plats commandés à l'intérieur) */
    if (count($lignes) > 0) {
        foreach ($lignes as $ligne) {

            $typeBadge = ($ligne['type_commande'] === 'sur_place') ? 'badge-surplace' : 'badge-emporter';
            $typeLibelle = ($ligne['type_commande'] === 'sur_place') ? 'Sur place' : 'Emporter';
            $dateFr = date('d/m/Y H:i', strtotime($ligne['date']));
            $classeStatut = classeStatut($ligne['statut']);
            $annulee = ($ligne['statut'] === 'annulee') ? ' ligne-annulee' : '';

            $table = ($ligne['numero_table'] !== null) ? 'Table n° ' . $ligne['numero_table'] : '';
            $client = ($ligne['nom_client'] !== null && $ligne['nom_client'] !== '') ? $ligne['nom_client'] : '—';
            $telephone = ($ligne['telephone'] !== null && $ligne['telephone'] !== '') ? $ligne['telephone'] : '—';

            $items = isset($itemsParCommande[$ligne['id']]) ? $itemsParCommande[$ligne['id']] : array();

            echo '<div class="carte' . $annulee . '">';
            echo '<div class="carte-tete">';
            echo '<span class="carte-ticket">Commande n° ' . (int)$ligne['id'] . '</span>';
            echo '<span class="badge ' . $typeBadge . '">' . $typeLibelle . '</span>';
            echo '<span class="badge-statut ' . $classeStatut . '">' . $libelleStatut[$ligne['statut']] . '</span>';
            echo '</div>';

            echo '<div class="carte-infos">';
            echo '<span class="sous-infos">' . htmlspecialchars($client);
            if ($table !== '') {
                echo ' · ' . htmlspecialchars($table);
            }
            if ($telephone !== '—') {
                echo ' · ' . htmlspecialchars($telephone);
            }
            echo '</span>';
            echo '</div>';

            echo '<ul class="carte-plats">';
            foreach ($items as $item) {
                $qte = ($item['quantite'] > 1) ? ' × ' . $item['quantite'] : '';
                echo '<li><strong>' . htmlspecialchars($item['menu']) . $qte . '</strong> — ' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' FCFA</li>';
            }
            echo '</ul>';

            echo '<div class="carte-date">' . $dateFr . '</div>';

            echo '<div class="carte-total">Total : ' . number_format($ligne['total'], 0, ',', ' ') . ' FCFA</div>';

            /* Actions de statut */
            echo '<div class="carte-actions">';
            echo '<a class="btn-action statut en-cours" href="ticket.php?id=' . (int)$ligne['id'] . '" target="_blank">Imprimer le ticket</a>';
            if ($ligne['statut'] !== 'en_cours') {
                echo '<a class="btn-action statut en-cours" href="index.php?action=statut&nouveau_statut=en_cours&id=' . (int)$ligne['id'] . '&csrf=' . urlencode(csrf_token()) . '">Remettre en cours</a>';
            }
            if ($ligne['statut'] !== 'annulee') {
                echo '<a class="btn-action statut livree" href="index.php?action=valider&id=' . (int)$ligne['id'] . '&csrf=' . urlencode(csrf_token()) . '">Marquer validé</a>';
            }
            if ($ligne['statut'] !== 'annulee') {
                echo '<a class="btn-action statut annulee" href="index.php?action=statut&nouveau_statut=annulee&id=' . (int)$ligne['id'] . '&csrf=' . urlencode(csrf_token()) . '">Annuler</a>';
            }
            echo '</div>';

            echo '</div>';
        }
    } else {
        echo '<div class="erreur">Aucune commande ne correspond à votre recherche.</div>';
    }
    ?>

</div>

<?php include '../footer.php'; ?>

<script>
    /* Détection des nouvelles commandes : interrogation toutes les 8 secondes */
    var cleStockage = 'restaurant_admin_max_id_vu';
    var maxIdVu = parseInt(localStorage.getItem(cleStockage), 10) || 0;
    var notification = document.getElementById('notification');
    var notificationAffichee = false;

    function verifierNouvellesCommandes() {
        fetch('../api-nouvelle.php')
            .then(function (reponse) { return reponse.json(); })
            .then(function (donnees) {
                if (donnees.maxId > maxIdVu) {
                    var nbNouvelles = donnees.maxId - maxIdVu;
                    maxIdVu = donnees.maxId;
                    localStorage.setItem(cleStockage, maxIdVu);
                    if (!notificationAffichee) {
                        notificationAffichee = true;
                        document.getElementById('notification-texte').textContent =
                            (nbNouvelles > 1)
                                ? nbNouvelles + ' nouvelles commandes reçues !'
                                : 'Nouvelle commande reçue !';
                        notification.style.display = 'flex';
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                }
            })
            .catch(function () {});
    }

    fetch('../api-nouvelle.php')
        .then(function (reponse) { return reponse.json(); })
        .then(function (donnees) {
            if (donnees.maxId > maxIdVu) {
                maxIdVu = donnees.maxId;
                localStorage.setItem(cleStockage, maxIdVu);
            }
        })
        .catch(function () {});

    setInterval(verifierNouvellesCommandes, 8000);
</script>

</body>

</html>
