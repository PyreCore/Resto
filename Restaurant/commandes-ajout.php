<?php require_once __DIR__ . '/config.php'; include 'theme.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=25">
    <?php echo style_theme(); ?>
    <title>Ajout d'une commande</title>
</head>

<body>

<div class="fond"></div>

<?php $pageCourante = 'commande'; include 'navbar.php'; ?>

<div class="container">

<?php
/**
 * Traitement du formulaire de nouvelle commande
 * Le menu est géré depuis l'administration (table « plats »).
 */

    // Le jeton CSRF du formulaire doit être présent et valide
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valider()) {
        die('<div class="erreur">Session expirée. Veuillez revenir au formulaire et réessayer.</div>');
    }

    $plats = [];
    $tablesDisponibles = [];
    try {
        $bddMenu = connexion_bdd();
        foreach ($bddMenu->query("SELECT * FROM plats ORDER BY categorie, nom") as $plat) {
            $plats[$plat['categorie']][$plat['nom']] = (int)$plat['prix'];
        }
        foreach ($bddMenu->query("SELECT numero FROM tables ORDER BY numero") as $t) {
            $tablesDisponibles[] = (int)$t['numero'];
        }
        $bddMenu = null;
    } catch (Exception $e) {
        // En cas de panne, le menu et les tables sont considérés vides.
    }

    // On récupère les plats de la corbeille et leurs quantités
    $menus = (array)($_POST['menu'] ?? []);
    $qtes = (array)($_POST['qte'] ?? []);

    // Validation de chaque plat : il doit exister dans le menu ; les doublons
    // sont regroupés (quantités additionnées) et le prix est récupéré en base
    $items = [];
    foreach ($menus as $i => $menu) {
        $menu = trim((string)$menu);
        if ($menu === '') {
            continue;
        }
        $quantite = isset($qtes[$i]) ? (int)$qtes[$i] : 1;
        if ($quantite < 1) {
            $quantite = 1;
        }
        $prix = null;
        foreach ($plats as $itemsCategorie) {
            if (array_key_exists($menu, $itemsCategorie)) {
                $prix = $itemsCategorie[$menu];
                break;
            }
        }
        // Le plat n'existe pas dans le menu : on refuse la commande
        if ($prix === null) {
            die('<div class="erreur">Le plat "'.htmlspecialchars($menu).'" est introuvable dans le menu.</div>');
        }
        if (isset($items[$menu])) {
            $items[$menu]['quantite'] += $quantite;
        } else {
            $items[$menu] = array('prix' => $prix, 'quantite' => $quantite);
        }
    }

    if (count($items) > 0) {
        // Validation du type de commande (sur place ou à emporter)
        $type = $_POST['type_commande'] ?? '';
        if ($type !== 'sur_place' && $type !== 'emporter') {
            die('<div class="erreur">Type de commande invalide.</div>');
        }

        $erreurs = array();
        $numeroTable = null;
        $nomClient = null;
        $telephone = null;

        // Téléphone : toujours obligatoire
        if (!isset($_POST['telephone']) || trim($_POST['telephone']) === '') {
            $erreurs[] = 'Veuillez indiquer le numéro de téléphone du client.';
        } else {
            $telephone = trim($_POST['telephone']);
        }

        if ($type === 'sur_place') {
            // Sur place : la table est obligatoire et doit exister
            $tableSaisie = (isset($_POST['numero_table']) && $_POST['numero_table'] !== '') ? trim($_POST['numero_table']) : '';
            if ($tableSaisie === '') {
                $erreurs[] = 'Veuillez choisir une table valide.';
            } elseif (!is_numeric($tableSaisie) || !in_array((int)$tableSaisie, $tablesDisponibles)) {
                $erreurs[] = 'Veuillez choisir une table valide.';
            } else {
                $numeroTable = (int)$tableSaisie;
            }
        }

        // Nom : obligatoire pour une commande à emporter
        $nomSaisi = isset($_POST['nom_client']) ? trim($_POST['nom_client']) : '';
        if ($nomSaisi !== '') {
            $nomClient = $nomSaisi;
        } elseif ($type === 'emporter') {
            $erreurs[] = 'Veuillez indiquer le nom du client pour une commande à emporter.';
        }

        if (count($erreurs) > 0) {
            echo '<div class="erreur">' . implode('<br/>', array_map('htmlspecialchars', $erreurs)) . '</div>';
            echo '<p class="note"><a href="commande.php">Retour au formulaire</a></p>';
            exit;
        }

        // Connexion à la base de données pour l'insertion
        try {
            $bdd = connexion_bdd();
        } catch (Exception $e) {
            die("Erreur de connexion à la base de données: <br/>" . $e->getMessage());
        }

        // Calcul du total de la commande
        $total = 0;
        foreach ($items as $item) {
            $total += $item['prix'] * $item['quantite'];
        }

        // Insertion dans une transaction : une commande (en-tête) dans
        // « commandes » et un enregistrement par plat dans « commandes_items »
        try {
            $bdd->beginTransaction();
            $reponse = $bdd->prepare("INSERT INTO commandes (type_commande, numero_table, nom_client, telephone, total) VALUES (:type_commande, :numero_table, :nom_client, :telephone, :total)");
            $reponse->execute(array(
                'type_commande' => $type,
                'numero_table' => $numeroTable,
                'nom_client' => $nomClient,
                'telephone' => $telephone,
                'total' => $total
            ));
            $commandeId = (int)$bdd->lastInsertId();

            $reponseItems = $bdd->prepare("INSERT INTO commandes_items (commande_id, menu, prix, quantite) VALUES (:commande_id, :menu, :prix, :quantite)");
            foreach ($items as $nom => $item) {
                $reponseItems->execute(array(
                    'commande_id' => $commandeId,
                    'menu' => $nom,
                    'prix' => $item['prix'],
                    'quantite' => $item['quantite']
                ));
            }

            $bdd->commit();
        } catch (Exception $e) {
            $bdd->rollBack();
            die('<div class="erreur">Erreur lors de l\'enregistrement de la commande : ' . htmlspecialchars($e->getMessage()) . '</div>');
        }

        // Affichage du message de confirmation et du détail de la commande
        $badgeType = ($type === 'sur_place') ? 'badge-surplace' : 'badge-emporter';
        $libelleType = ($type === 'sur_place') ? 'Sur place' : 'Emporter';
        $infos = array();
        if ($type === 'sur_place' && $numeroTable !== null) {
            $infos[] = 'Table n° ' . $numeroTable;
        }
        if ($nomClient !== null && $nomClient !== '') {
            $infos[] = 'Client : ' . htmlspecialchars($nomClient);
        }
        if ($telephone !== null && $telephone !== '') {
            $infos[] = 'Téléphone : ' . htmlspecialchars($telephone);
        }

        echo '<div class="succes">Commande enregistrée avec succès !</div>';
        echo '<div class="carte">';
        echo '<div class="carte-tete"><span class="carte-ticket">Votre commande n° ' . $commandeId . '</span><span class="badge ' . $badgeType . '">' . $libelleType . '</span></div>';
        echo '<div class="carte-infos">' . implode(' · ', $infos) . '</div>';
        echo '<ul class="carte-plats">';
        foreach ($items as $nom => $item) {
            $qte = ($item['quantite'] > 1) ? ' × ' . $item['quantite'] : '';
            echo '<li><strong>' . htmlspecialchars($nom) . $qte . '</strong> — ' . number_format($item['prix'] * $item['quantite'], 0, ',', ' ') . ' FCFA</li>';
        }
        echo '</ul>';
        echo '<div class="carte-total">Total : ' . number_format($total, 0, ',', ' ') . ' FCFA</div>';
        echo '</div>';
        echo '<p class="note"><a href="commande.php">Passer une nouvelle commande</a> · <a href="mes-commandes.php">Voir mes commandes</a></p>';
    } else {
        // Aucun plat dans la corbeille
        die('<div class="erreur">Veuillez ajouter au moins un plat à la corbeille.</div>');
    }

?>

</div>

<?php include 'footer.php'; ?>

</body>

</html>
