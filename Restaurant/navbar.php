<?php
/* Barre de navigation du site client */
require_once __DIR__ . '/config.php';
$pageCourante = isset($pageCourante) ? $pageCourante : '';

/* Sur le site client, la session admin est coupée :
   un client ne peut donc jamais voir les commandes sans mot de passe. */
demarrer_session();
if (isset($_SESSION['admin_connecte'])) {
    session_destroy();
    demarrer_session();
}
?>
<nav class="navbar">
    <!-- Logo : retour à la nouvelle commande -->
    <a class="navbar-logo" href="commande.php"> LeDélise</a>
    <div class="navbar-liens">
        <!-- Lien de la page courante (nouvelle commande) -->
        <a href="commande.php"<?php echo ($pageCourante === 'commande') ? ' class="actif"' : ''; ?>>
            Nouvelle commande
        </a>
        <!-- Suivi des commandes du client -->
        <a href="mes-commandes.php"<?php echo ($pageCourante === 'mes-commandes') ? ' class="actif"' : ''; ?>>
            Mes commandes
        </a>
    </div>
</nav>
