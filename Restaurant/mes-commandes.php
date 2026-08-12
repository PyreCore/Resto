<?php require_once __DIR__ . '/config.php'; $pageCourante = 'mes-commandes'; include 'theme.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=25">
    <?php echo style_theme(); ?>
    <title>Mes commandes - LeDélise</title>
</head>

<body>

<div class="fond"></div>

<?php include 'navbar.php'; ?>

<div class="container">

    <h1>Mes commandes</h1>
    <p class="note">Saisissez le numéro de téléphone utilisé lors de votre commande.</p>

    <!-- Recherche par numéro de téléphone -->
    <form method="get" action="mes-commandes.php" class="filtres">
        <input type="tel" name="telephone" placeholder="Ex. : +24165005000"
               value="<?php echo htmlspecialchars(isset($_GET['telephone']) ? trim($_GET['telephone']) : ''); ?>" required/>
        <button type="submit">Voir mes commandes</button>
    </form>

    <?php
    $telephone = isset($_GET['telephone']) ? trim($_GET['telephone']) : '';

    if ($telephone !== ''):

        $commandes = array();
        $itemsParCommande = array();
        $itemsParArchive = array();

        try {
            $bdd = connexion_bdd();

            /* Commandes en cours (commandes) + commandes validées (commandes_archivees),
               recherchées par numéro de téléphone puis fusionnées et triées par date */
            $stmt = $bdd->prepare("SELECT * FROM commandes WHERE telephone=:telephone ORDER BY date DESC");
            $stmt->execute(array(':telephone' => $telephone));
            foreach ($stmt as $c) {
                $c['numero'] = (int)$c['id'];
                $c['ts'] = strtotime($c['date']);
                $c['validee'] = false;
                $commandes[] = $c;
            }
            $stmt->closeCursor();

            $stmt = $bdd->prepare("SELECT * FROM commandes_archivees WHERE telephone=:telephone ORDER BY date_validation DESC");
            $stmt->execute(array(':telephone' => $telephone));
            foreach ($stmt as $c) {
                $c['numero'] = ($c['commande_origine'] !== null) ? (int)$c['commande_origine'] : (int)$c['id'];
                $c['ts'] = strtotime($c['date_validation']);
                $c['statut'] = 'validee';
                $c['validee'] = true;
                $commandes[] = $c;
            }
            $stmt->closeCursor();

            /* Plats des commandes en cours et des commandes archivées */
            $stmt = $bdd->query("SELECT * FROM commandes_items ORDER BY commande_id, id");
            foreach ($stmt as $it) {
                $itemsParCommande[$it['commande_id']][] = $it;
            }
            $stmt->closeCursor();

            $stmt = $bdd->query("SELECT * FROM commandes_archivees_items ORDER BY archive_id, id");
            foreach ($stmt as $it) {
                $itemsParArchive[$it['archive_id']][] = $it;
            }
            $stmt->closeCursor();

            $bdd = null;
        } catch (Exception $e) {
            /* Base inaccessible : liste vide */
        }

        /* Tri des commandes par date décroissante */
        usort($commandes, function ($a, $b) {
            return $b['ts'] - $a['ts'];
        });

        $libelleStatut = array(
            'en_cours' => 'En cours',
            'livree' => 'Livrée',
            'annulee' => 'Annulée',
            'validee' => 'Validée'
        );

        $classeStatut = array(
            'en_cours' => 'en-cours',
            'livree' => 'livree',
            'annulee' => 'annulee',
            'validee' => 'livree'
        );

        if (count($commandes) === 0):
    ?>

    <div class="erreur">Aucune commande trouvée pour ce numéro de téléphone.</div>

    <?php else: ?>

    <?php foreach ($commandes as $commande): ?>
    <?php
        $typeBadge = ($commande['type_commande'] === 'sur_place') ? 'badge-surplace' : 'badge-emporter';
        $typeLibelle = ($commande['type_commande'] === 'sur_place') ? 'Sur place' : 'Emporter';
        $dateFr = date('d/m/Y H:i', $commande['ts']);
        $table = ($commande['numero_table'] !== null) ? 'Table n° ' . $commande['numero_table'] : '';
        $client = ($commande['nom_client'] !== null && $commande['nom_client'] !== '')
            ? htmlspecialchars($commande['nom_client']) : '—';
        $items = $commande['validee']
            ? (isset($itemsParArchive[$commande['id']]) ? $itemsParArchive[$commande['id']] : array())
            : (isset($itemsParCommande[$commande['id']]) ? $itemsParCommande[$commande['id']] : array());
    ?>
    <div class="carte">
        <div class="carte-tete">
            <span class="carte-ticket">Commande n° <?php echo $commande['numero']; ?></span>
            <span class="badge <?php echo $typeBadge; ?>"><?php echo $typeLibelle; ?></span>
            <span class="badge-statut <?php echo $classeStatut[$commande['statut']]; ?>"><?php echo $libelleStatut[$commande['statut']]; ?></span>
        </div>
        <div class="carte-infos">
            <?php echo $client;
                if ($table !== '') {
                    echo ' · ' . htmlspecialchars($table);
                }
                echo ' · ' . htmlspecialchars($commande['telephone']);
            ?>
        </div>
        <ul class="carte-plats">
            <?php foreach ($items as $item): ?>
            <?php $qte = ($item['quantite'] > 1) ? ' × ' . $item['quantite'] : ''; ?>
            <li><strong><?php echo htmlspecialchars($item['menu']); ?><?php echo $qte; ?></strong> — <?php echo number_format($item['prix'] * $item['quantite'], 0, ',', ' '); ?> FCFA</li>
            <?php endforeach; ?>
        </ul>
        <div class="carte-date"><?php echo $dateFr; ?></div>
        <div class="carte-total">Total : <?php echo number_format($commande['total'], 0, ',', ' '); ?> FCFA</div>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>

</body>

</html>
