<?php
/* ============================================
   admin/parametres.php - Couleurs du site
   Permet de changer les couleurs du thème : thèmes
   prédéfinis ou couleurs personnalisées. Les valeurs
   sont enregistrées dans la table « parametres » et
   appliquées par theme.php sur toutes les pages.
   ============================================ */

include 'protection.php';
include '../theme.php';

/* Connexion à la base de données */
try {
    $bdd = connexion_bdd();
} catch (Exception $e) {
    die("Erreur de connexion à la base de données: <br/>" . $e->getMessage());
}

$clesCouleurs = array('couleur_fond', 'couleur_principale', 'couleur_accent',
                      'couleur_accent_fonce', 'couleur_accent_clair');

/* Enregistrement des couleurs personnalisées / réinitialisation du thème :
   le jeton CSRF du formulaire doit être valide */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valider()) {
    $message = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
} else {
if (isset($_POST['enregistrer'])) {
    $invalide = false;
    foreach ($clesCouleurs as $cle) {
        if (!isset($_POST[$cle]) || !preg_match('/^#[0-9a-fA-F]{6}$/', $_POST[$cle])) {
            $invalide = true;
            break;
        }
    }
    if ($invalide) {
        $message = '<div class="erreur">Une couleur est invalide. Vérifiez le format (#RRGGBB).</div>';
    } else {
        foreach ($clesCouleurs as $cle) {
            $bdd->prepare('INSERT INTO parametres (cle, valeur) VALUES (:cle, :valeur)
                           ON DUPLICATE KEY UPDATE valeur = :valeur2')
                ->execute(array(
                    ':cle'     => $cle,
                    ':valeur'  => strtoupper($_POST[$cle]),
                    ':valeur2' => strtoupper($_POST[$cle])
                ));
        }
        $message = '<div class="succes">Les couleurs ont bien été enregistrées. Elles s\'appliquent à tout le site.</div>';
    }
}

/* Réinitialisation du thème par défaut (brun chaleureux) */
if (isset($_POST['reinitialiser'])) {
    $parDefaut = array(
        'couleur_fond'         => '#2B1D12',
        'couleur_principale'   => '#A54A12',
        'couleur_accent'       => '#C05621',
        'couleur_accent_fonce' => '#7C3E12',
        'couleur_accent_clair' => '#E0A56B',
    );
    foreach ($parDefaut as $cle => $valeur) {
        $bdd->prepare('INSERT INTO parametres (cle, valeur) VALUES (:cle, :valeur)
                       ON DUPLICATE KEY UPDATE valeur = :valeur2')
            ->execute(array(':cle' => $cle, ':valeur' => $valeur, ':valeur2' => $valeur));
    }
    $message = '<div class="succes">Le thème par défaut « Brun chaleureux » a été rétabli.</div>';
}
}

/* Couleurs actuellement enregistrées */
$couleurs = couleurs_theme();
$bdd = null;

/* Thèmes prédéfinis proposés (palettes harmonieuses pour un restaurant) */
$themes = array(
    array('nom' => 'Brun chaleureux',    'fond' => '#2B1D12', 'principale' => '#A54A12', 'accent' => '#C05621', 'fonce' => '#7C3E12', 'clair' => '#E0A56B'),
    array('nom' => 'Vert naturel',       'fond' => '#17281C', 'principale' => '#388E3C', 'accent' => '#43A047', 'fonce' => '#1B5E20', 'clair' => '#A5D6A7'),
    array('nom' => 'Bleu océan',         'fond' => '#14232E', 'principale' => '#1976D2', 'accent' => '#1E88E5', 'fonce' => '#0D47A1', 'clair' => '#90CAF9'),
    array('nom' => 'Bordeaux élégant',   'fond' => '#2A1113', 'principale' => '#C62828', 'accent' => '#E53935', 'fonce' => '#8E1216', 'clair' => '#EF9A9A'),
    array('nom' => 'Violet prestige',    'fond' => '#201430', 'principale' => '#6A1B9A', 'accent' => '#8E24AA', 'fonce' => '#4A148C', 'clair' => '#CE93D8'),
    array('nom' => 'Orange épicé',       'fond' => '#2B1D0F', 'principale' => '#E65100', 'accent' => '#EF6C00', 'fonce' => '#BF360C', 'clair' => '#FFCC80'),
    array('nom' => 'Noir & or',          'fond' => '#141414', 'principale' => '#B8860B', 'accent' => '#D4AF37', 'fonce' => '#8F6C10', 'clair' => '#F0D77B'),
    array('nom' => 'Gris ardoise',       'fond' => '#1B1D21', 'principale' => '#546E7A', 'accent' => '#607D8B', 'fonce' => '#37474F', 'clair' => '#B0BEC5'),
);

/* Libellés des couleurs */
$libelles = array(
    'couleur_fond'         => 'Fond sombre (pages, menu, barres)',
    'couleur_principale'   => 'Couleur principale (titres, liens, montants)',
    'couleur_accent'       => 'Couleur d\'accent (boutons, actions)',
    'couleur_accent_fonce' => 'Accent foncé (étiquettes, en-têtes de tableau)',
    'couleur_accent_clair' => 'Accent clair (flèches, dégradés, logo)',
);

$pageCourante = 'couleurs';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=24">
    <?php echo style_theme(); ?>
    <title>Couleurs du site - LeDélise</title>
</head>

<body class="page-admin">

<div class="fond"></div>

<?php include 'navbar.php'; ?>

<div class="container admin">

    <h1>Couleurs du site</h1>
    <p class="note">Choisissez un thème prédéfini ou personnalisez les couleurs : elles s'appliquent immédiatement à tout le site (client et administration).</p>

    <hr/>

    <?php echo isset($message) ? $message : ''; ?>

    <div class="bloc-theme">
        <h3 class="titre-section">Thèmes prédéfinis</h3>
        <div class="grille-themes">
            <?php foreach ($themes as $i => $t): ?>
            <button type="button" class="theme-carte" data-theme="<?php echo $i; ?>">
                <span class="theme-echantillon">
                    <i style="background:<?php echo $t['fond']; ?>"></i>
                    <i style="background:<?php echo $t['principale']; ?>"></i>
                    <i style="background:<?php echo $t['accent']; ?>"></i>
                    <i style="background:<?php echo $t['clair']; ?>"></i>
                </span>
                <span class="theme-nom"><?php echo $t['nom']; ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bloc-theme">
        <h3 class="titre-section">Couleurs personnalisées</h3>
        <form method="post" action="parametres.php" id="form-couleurs">
            <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>"/>
            <div class="grille-couleurs">
                <?php foreach ($clesCouleurs as $cle): ?>
                <div class="champ-couleur">
                    <label for="<?php echo $cle; ?>"><?php echo $libelles[$cle]; ?></label>
                    <input type="color" id="<?php echo $cle; ?>" name="<?php echo $cle; ?>"
                           value="<?php echo $couleurs[$cle]; ?>"/>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="apercu-theme">
                <div class="apercu-nav">
                    <span>LeDélise</span>
                    <div class="apercu-nav-liens">
                        <a href="#">Administration</a>
                        <a href="#">Historique</a>
                    </div>
                </div>
                <h4>Aperçu du thème</h4>
                <div class="apercu-ligne">
                    <button type="button" class="apercu-bouton">Bouton principal</button>
                    <button type="button" class="apercu-bouton secondaire">Bouton secondaire</button>
                    <span class="apercu-badge">Sur place</span>
                </div>
                <div class="apercu-carte"><strong>Total : 3 300 FCFA</strong></div>
            </div>

            <div class="ligne-actions">
                <button type="submit" name="enregistrer" value="1" class="btn-plat">Enregistrer les couleurs</button>
                <button type="submit" name="reinitialiser" value="1" class="btn-action reset">Réinitialiser</button>
            </div>
        </form>
    </div>

</div>

<?php include '../footer.php'; ?>

<script>
    /* Prévisualisation en direct : met à jour les variables CSS du thème */
    var clesCouleurs = ['couleur_fond', 'couleur_principale', 'couleur_accent',
                        'couleur_accent_fonce', 'couleur_accent_clair'];
    var variablesCSS = {
        couleur_fond: '--c-fond',
        couleur_principale: '--c-principale',
        couleur_accent: '--c-accent',
        couleur_accent_fonce: '--c-accent-fonce',
        couleur_accent_clair: '--c-accent-clair'
    };

    function appliquerCouleurs() {
        var racine = document.documentElement.style;
        clesCouleurs.forEach(function (cle) {
            var input = document.getElementById(cle);
            racine.setProperty(variablesCSS[cle], input.value);
        });
        /* La barre de navigation suit le fond sombre (~95 % d'opacité) */
        racine.setProperty('--c-fond-nav', document.getElementById('couleur_fond').value + 'F2');
    }

    clesCouleurs.forEach(function (cle) {
        document.getElementById(cle).addEventListener('input', appliquerCouleurs);
    });

    /* Application d'un thème prédéfini */
    var themes = <?php echo json_encode($themes); ?>;
    document.querySelectorAll('.theme-carte').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            var t = themes[this.dataset.theme];
            document.getElementById('couleur_fond').value = t.fond;
            document.getElementById('couleur_principale').value = t.principale;
            document.getElementById('couleur_accent').value = t.accent;
            document.getElementById('couleur_accent_fonce').value = t.fonce;
            document.getElementById('couleur_accent_clair').value = t.clair;
            appliquerCouleurs();
        });
    });
</script>

</body>

</html>
