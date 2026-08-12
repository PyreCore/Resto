<?php
/* Vérification de l'accès à l'administration : redirige vers la page de connexion si non connecté */
require_once __DIR__ . '/../config.php';
demarrer_session();

if (!isset($_SESSION['admin_connecte']) || $_SESSION['admin_connecte'] !== true) {
    header('Location: login.php');
    exit;
}

/* Expiration : après 5 minutes d'inactivité, le mot de passe est redemandé */
if (!isset($_SESSION['admin_expire'])) {
    $_SESSION['admin_expire'] = time() + ADMIN_EXPIRATION;
}
if (time() > $_SESSION['admin_expire']) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['admin_expire'] = time() + ADMIN_EXPIRATION;
