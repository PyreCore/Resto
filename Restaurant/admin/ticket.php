<?php
include 'protection.php';

$commande = null;
$items = array();

/* Chargement de la commande demandée (?id=) et de ses plats */
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $bdd = connexion_bdd();
        $stmt = $bdd->prepare("SELECT * FROM commandes WHERE id=:id");
        $stmt->execute(array(':id' => (int)$_GET['id']));
        $commande = $stmt->fetch();
        $stmt->closeCursor();
        if ($commande) {
            $stmtItems = $bdd->prepare("SELECT * FROM commandes_items WHERE commande_id=:commande_id ORDER BY id");
            $stmtItems->execute(array(':commande_id' => (int)$_GET['id']));
            $items = $stmtItems->fetchAll();
            $stmtItems->closeCursor();
        }
        $bdd = null;
    } catch (Exception $e) {
        $commande = null;
    }
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
    <title>Ticket de commande - LeDélise</title>
</head>

<body class="page-ticket-body">

<div class="fond"></div>

<?php if ($commande): ?>

<!-- Barre d'actions (masquée à l'impression) -->
<div class="ticket-toolbar no-print">
    <a class="btn-action" href="index.php">&larr; Retour</a>
    <button type="button" onclick="window.print()" class="btn-action imprimer">Imprimer le ticket</button>
</div>

<?php
    $typeLibelle = ($commande['type_commande'] === 'sur_place') ? 'Sur place' : 'Emporter';
    $dateTicket = date('d/m/Y H:i', strtotime($commande['date']));
    $table = ($commande['numero_table'] !== null) ? 'Table n&deg; ' . $commande['numero_table'] : '';
    $client = ($commande['nom_client'] !== null && $commande['nom_client'] !== '')
        ? htmlspecialchars($commande['nom_client']) : '&mdash;';
    $telephone = ($commande['telephone'] !== null && $commande['telephone'] !== '')
        ? htmlspecialchars($commande['telephone']) : '&mdash;';
?>

<!-- Ticket de caisse compact -->
<div class="ticket">
    <h1>LeD&eacute;lise</h1>
    <p class="ticket-sous-titre">Ticket de commande</p>
    <p class="ticket-sous-titre"><?php echo $dateTicket; ?></p>

    <hr class="ticket-separateur"/>

    <div class="ticket-ligne"><span>N&deg; commande</span><strong><?php echo (int)$commande['id']; ?></strong></div>
    <div class="ticket-ligne"><span>Type</span><strong><?php echo $typeLibelle; ?></strong></div>
    <?php if ($table !== ''): ?>
    <div class="ticket-ligne"><span>Table</span><strong><?php echo $table; ?></strong></div>
    <?php endif; ?>
    <div class="ticket-ligne"><span>Client</span><strong><?php echo $client; ?></strong></div>
    <div class="ticket-ligne"><span>T&eacute;l&eacute;phone</span><strong><?php echo $telephone; ?></strong></div>

    <hr class="ticket-separateur"/>

    <ul class="ticket-liste">
        <?php foreach ($items as $item): ?>
        <li>
            <span class="nom">
                <?php echo htmlspecialchars($item['menu']); ?>
                <?php echo ($item['quantite'] > 1) ? ' x ' . (int)$item['quantite'] : ''; ?>
            </span>
            <span class="prix"><?php echo number_format($item['prix'] * $item['quantite'], 0, ',', ' '); ?> F</span>
        </li>
        <?php endforeach; ?>
    </ul>

    <hr class="ticket-separateur"/>

    <div class="ticket-total">
        <span>TOTAL</span>
        <span><?php echo number_format($commande['total'], 0, ',', ' '); ?> FCFA</span>
    </div>

    <p class="ticket-pied">Merci de votre visite &mdash; Bon app&eacute;tit !</p>
</div>

<?php else: ?>

<div class="container admin">
    <h1>Ticket de commande</h1>
    <div class="erreur">Commande introuvable.</div>
    <p class="note"><a href="index.php">Retour au tableau de bord</a></p>
</div>

<?php endif; ?>

</body>

</html>
