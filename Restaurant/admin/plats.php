<?php
include 'protection.php';

$pageCourante = 'plats';

/* Connexion à la base de données */
try {
    $bdd = connexion_bdd();
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: <br/>" . $e->getMessage());
}

$message = '';

/* Ajout d'une table (formulaire « nouvelle table ») et ajout/modification
   d'un plat : le jeton CSRF du formulaire doit être valide */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valider()) {
    $message = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
} elseif (isset($_POST['nouvelle_table'])) {
    $nouvelleTable = trim($_POST['nouvelle_table']);
    if ($nouvelleTable === '' || !is_numeric($nouvelleTable) || (int)$nouvelleTable <= 0) {
        $message = '<div class="erreur">Le numéro de table doit être un nombre positif.</div>';
    } elseif ((int)$bdd->query("SELECT COUNT(*) FROM tables WHERE numero=" . (int)$nouvelleTable)->fetchColumn() > 0) {
        $message = '<div class="erreur">La table n° ' . (int)$nouvelleTable . ' existe déjà.</div>';
    } else {
        $bdd->prepare("INSERT INTO tables (numero) VALUES (:numero)")
            ->execute(array(':numero' => (int)$nouvelleTable));
        $message = '<div class="succes">La table n° ' . (int)$nouvelleTable . ' a bien été ajoutée.</div>';
    }
}

/* Ajout ou modification d'un plat (envoi du formulaire) */
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categorie = isset($_POST['categorie']) ? trim($_POST['categorie']) : '';
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prix = isset($_POST['prix']) ? $_POST['prix'] : '';

    /* La catégorie est toujours obligatoire pour un plat */
    if ($categorie === '') {
        $categorie = 'Autre';
    }

    if ($nom === '') {
        $message = '<div class="erreur">Veuillez renseigner le nom du plat.</div>';
    } elseif ($categorie === '') {
        $message = '<div class="erreur">Veuillez renseigner la catégorie du plat.</div>';
    } elseif (!is_numeric($prix) || (int)$prix <= 0) {
        $message = '<div class="erreur">Le prix doit être un nombre positif.</div>';
    } elseif (isset($_POST['id']) && is_numeric($_POST['id'])) {
        /* Modification d'un plat existant */
        $bdd->prepare("UPDATE plats SET categorie=:categorie, nom=:nom, prix=:prix WHERE id=:id")
            ->execute(array(
                ':categorie' => $categorie,
                ':nom' => $nom,
                ':prix' => (int)$prix,
                ':id' => (int)$_POST['id']
            ));
        $message = '<div class="succes">Le plat a bien été modifié.</div>';
    } else {
        /* Ajout d'un nouveau plat */
        $bdd->prepare("INSERT INTO plats (categorie, nom, prix) VALUES (:categorie, :nom, :prix)")
            ->execute(array(
                ':categorie' => $categorie,
                ':nom' => $nom,
                ':prix' => (int)$prix
            ));
        $message = '<div class="succes">Le plat a bien été ajouté.</div>';
    }
}

/* Suppression d'un plat (action via lien GET, jeton CSRF requis) */
if (isset($_GET['action']) && $_GET['action'] === 'supprimer'
    && isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!csrf_valider()) {
        $message = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
    } else {
        $bdd->prepare("DELETE FROM plats WHERE id=:id")
            ->execute(array(':id' => (int)$_GET['id']));
        $message = '<div class="succes">Le plat a bien été supprimé.</div>';
    }
}

/* Suppression d'une table (action via lien GET, jeton CSRF requis) */
if (isset($_GET['action']) && $_GET['action'] === 'supprimer_table'
    && isset($_GET['id']) && is_numeric($_GET['id'])) {
    if (!csrf_valider()) {
        $message = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
    } else {
        $bdd->prepare("DELETE FROM tables WHERE id=:id")
            ->execute(array(':id' => (int)$_GET['id']));
        $message = '<div class="succes">La table a bien été retirée.</div>';
    }
}

/* Plat à modifier (pré-remplissage du formulaire) */
$platModif = null;
if (isset($_GET['action']) && $_GET['action'] === 'modifier'
    && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $bdd->prepare("SELECT * FROM plats WHERE id=:id");
    $stmt->execute(array(':id' => (int)$_GET['id']));
    $platModif = $stmt->fetch();
    $stmt->closeCursor();
}

/* Liste des plats groupés par catégorie */
$groupes = array();
$plats = $bdd->query("SELECT * FROM plats ORDER BY categorie, nom")->fetchAll();
foreach ($plats as $p) {
    $groupes[$p['categorie']][] = $p;
}

/* Liste des tables de la salle */
$tables = $bdd->query("SELECT id, numero FROM tables ORDER BY numero")->fetchAll();
$bdd = null;

include '../theme.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=25">
    <?php echo style_theme(); ?>
    <title>Gestion des plats et des tables - LeDélise</title>
</head>

<body class="page-admin">

<div class="fond"></div>

<?php include 'navbar.php'; ?>

<div class="container admin">

    <h1>Gestion des plats et des tables</h1>
    <p class="note">Les plats du menu et les tables de la salle affichés lors de la création d'une commande.</p>

    <hr/>

    <?php echo $message; ?>

    <!-- Disposition côte à côte : plats à gauche, tables à droite -->
    <div class="layout-gestion">

        <div class="colonne-gauche">

            <div class="bloc-plats">

                <!-- Formulaire d'ajout ou de modification d'un plat -->
                <form method="post" action="plats.php" class="formulaire-plat">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>"/>
                    <input type="hidden" name="id" value="<?php echo $platModif ? (int)$platModif['id'] : ''; ?>"/>
                    <div class="champ-plat">
                        <label for="categorie">Catégorie</label>
                        <input type="text" id="categorie" name="categorie"
                               value="<?php echo htmlspecialchars($platModif ? $platModif['categorie'] : ''); ?>"
                               placeholder="Ex. : Entrées" required/>
                    </div>
                    <div class="champ-plat">
                        <label for="nom">Nom du plat</label>
                        <input type="text" id="nom" name="nom"
                               value="<?php echo htmlspecialchars($platModif ? $platModif['nom'] : ''); ?>"
                               placeholder="Ex. : Salade de choux" required/>
                    </div>
                    <div class="champ-plat">
                        <label for="prix">Prix (FCFA)</label>
                        <input type="number" id="prix" name="prix" min="1" step="1"
                               value="<?php echo $platModif ? (int)$platModif['prix'] : ''; ?>"
                               placeholder="Ex. : 500" required/>
                    </div>
                    <button type="submit" class="btn-plat">
                        <?php echo $platModif ? 'Modifier le plat' : 'Ajouter le plat'; ?>
                    </button>
                    <?php if ($platModif): ?>
                    <a class="btn-action reset" href="plats.php">Annuler la modification</a>
                    <?php endif; ?>
                </form>

                <?php if (count($groupes) > 0): ?>

                <!-- Liste des plats groupés par catégorie -->
                <div class="liste-plats">
                    <?php foreach ($groupes as $categorie => $items): ?>
                    <div class="groupe-plats">
                        <h2 class="titre-categorie"><?php echo htmlspecialchars($categorie); ?></h2>
                        <div class="zone-table">
                            <table class="table-commandes">
                                <tbody>
                                    <?php foreach ($items as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['nom']); ?></td>
                                        <td class="montant-total"><?php echo number_format($p['prix'], 0, ',', ' '); ?> F</td>
                                        <td class="cellule-actions">
                                            <a class="btn-action statut en-cours" href="plats.php?action=modifier&id=<?php echo (int)$p['id']; ?>">Modifier</a>
                                            <a class="btn-action supprimer" href="plats.php?action=supprimer&id=<?php echo (int)$p['id']; ?>&csrf=<?php echo urlencode(csrf_token()); ?>"
                                               onclick="return confirm('Supprimer le plat « <?php echo htmlspecialchars(addslashes($p['nom'])); ?> » ?');">Supprimer</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php else: ?>
                <div class="erreur">Aucun plat dans le menu. Ajoutez-en un avec le formulaire ci-dessus.</div>
                <?php endif; ?>

            </div>

        </div>

        <div class="colonne-droite">

            <!-- Gestion des tables de la salle -->
            <div class="groupe-plats bloc-tables">
                <h2 class="titre-categorie">Tables de la salle</h2>
                <form method="post" action="plats.php" class="filtres">
                    <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>"/>
                    <input type="number" name="nouvelle_table" min="1" step="1"
                           placeholder="N° de table à ajouter (ex. : 11)" required/>
                    <button type="submit">Ajouter la table</button>
                </form>
                <?php if (count($tables) > 0): ?>
                <div class="zone-table">
                    <table class="table-commandes">
                        <tbody>
                            <?php foreach ($tables as $t): ?>
                            <tr>
                                <td>Table n° <?php echo (int)$t['numero']; ?></td>
                                <td class="cellule-actions">
                                    <a class="btn-action supprimer" href="plats.php?action=supprimer_table&id=<?php echo (int)$t['id']; ?>&csrf=<?php echo urlencode(csrf_token()); ?>"
                                       onclick="return confirm('Retirer la table n° <?php echo (int)$t['numero']; ?> ?');">Retirer</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="erreur">Aucune table enregistrée. Ajoutez-en une avec le formulaire ci-dessus.</div>
                <?php endif; ?>
            </div>

        </div>

    </div>

</div>

<?php include '../footer.php'; ?>

</body>

</html>
