<?php
/* Page de connexion à l'administration */

require_once __DIR__ . '/../config.php';
demarrer_session();

/* Nombre maximal d'essais consécutifs avant blocage temporaire */
define('LOGIN_ESSAIS_MAX', 5);
define('LOGIN_BLOCAGE', 300); /* durée de blocage en secondes */

/* Déconnexion : on détruit la session, puis on retourne vers la page indiquée.
   La cible de redirection est limitée à une liste blanche pour empêcher les
   redirections arbitraires (open redirect) et l'injection d'en-têtes. */
if (isset($_GET['deconnexion'])) {
    session_destroy();
    $retours = array('login.php' => 1, 'index.php' => 1, '../commande.php' => 1, '../mes-commandes.php' => 1);
    $retour = (isset($_GET['retour']) && isset($retours[$_GET['retour']])) ? $_GET['retour'] : 'login.php';
    header('Location: ' . $retour);
    exit;
}

/* Déjà connecté : on envoie directement vers l'administration */
if (isset($_SESSION['admin_connecte']) && $_SESSION['admin_connecte'] === true) {
    header('Location: index.php');
    exit;
}

$erreur = '';

/* Vérification du mot de passe saisi (avec blocage temporaire en cas
   de tentatives répétées, pour ralentir les attaques par force brute) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenant = time();

    /* Le jeton CSRF du formulaire doit être présent et valide */
    if (!csrf_valider()) {
        $erreur = '<div class="erreur">Session expirée. Veuillez réessayer.</div>';
    }

    /* Compteur d'essais : si le blocage est actif, on ignore toute nouvelle tentative */
    elseif (isset($_SESSION['login_bloque_jusqua']) && $maintenant < $_SESSION['login_bloque_jusqua']) {
        $minutes = (int)ceil(($_SESSION['login_bloque_jusqua'] - $maintenant) / 60);
        $erreur = '<div class="erreur">Trop de tentatives. Réessayez dans ' . $minutes . ' minute(s).</div>';
    } else {
        /* Si un blocage venait d'expirer, on repart de zéro */
        if (isset($_SESSION['login_bloque_jusqua'])) {
            unset($_SESSION['login_essais'], $_SESSION['login_bloque_jusqua']);
        }

        $saisie = isset($_POST['mot_de_passe']) ? $_POST['mot_de_passe'] : '';
        /* Vérification : mot de passe haché (password_verify) si le hash est
           défini, sinon comparaison à temps constant du mot de passe en clair */
        $valide = (defined('ADMIN_MOT_DE_PASSE_HASH') && ADMIN_MOT_DE_PASSE_HASH !== '')
            ? password_verify($saisie, ADMIN_MOT_DE_PASSE_HASH)
            : hash_equals(ADMIN_MOT_DE_PASSE, $saisie);
        if ($valide) {
            /* Connexion réussie : on renouvelle l'identifiant de session
               (prévient le détournement de session) et on repart à zéro */
            session_regenerate_id(true);
            unset($_SESSION['login_essais'], $_SESSION['login_bloque_jusqua']);
            $_SESSION['admin_connecte'] = true;
            $_SESSION['admin_expire'] = time() + ADMIN_EXPIRATION;
            header('Location: index.php');
            exit;
        }
        /* Mauvais mot de passe : on incrémente le compteur et on bloque à partir du 5e échec */
        $essais = (int)(isset($_SESSION['login_essais']) ? $_SESSION['login_essais'] : 0) + 1;
        $_SESSION['login_essais'] = $essais;
        if ($essais >= LOGIN_ESSAIS_MAX) {
            $_SESSION['login_bloque_jusqua'] = $maintenant + LOGIN_BLOCAGE;
            $essais = 0;
            $erreur = '<div class="erreur">Trop de tentatives. Réessayez dans ' . (int)(LOGIN_BLOCAGE / 60) . ' minutes.</div>';
        } else {
            $erreur = '<div class="erreur">Mot de passe incorrect.</div>';
        }
    }
}

include '../theme.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=24">
    <?php echo style_theme(); ?>
    <title>Connexion - LeDélise</title>
</head>

<body class="page-admin">

<div class="fond"></div>

<div class="container" style="margin-top:20px;">

    <h1>Administration</h1>
    <p class="note">Entrez le mot de passe pour accéder à l'administration.</p>

    <hr/>

    <?php echo $erreur; ?>

    <form method="post" action="login.php" class="form-login">
        <input type="hidden" name="csrf" value="<?php echo csrf_token(); ?>"/>
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required autofocus/>
        <button type="submit">Se connecter</button>
    </form>

    <p class="note"><a href="../commande.php">Retour au site client</a></p>

</div>

<?php include '../footer.php'; ?>

</body>

</html>
