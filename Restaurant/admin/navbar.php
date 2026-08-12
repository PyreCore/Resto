<?php
/* Barre de navigation de l'administration */
$pageCourante = isset($pageCourante) ? $pageCourante : '';
?>
<nav class="navbar">
    <!-- Logo : retour à l'accueil de l'administration -->
    <a class="navbar-logo" href="index.php"> LeDélise</a>
    <div class="navbar-liens">
        <!-- Lien actif selon la page en cours -->
        <a href="index.php"<?php echo ($pageCourante === 'admin') ? ' class="actif"' : ''; ?>>
            Administration<span class="badge-nb" id="badge-nb-commandes" style="display:none;"></span>
        </a>
        <a href="plats.php"<?php echo ($pageCourante === 'plats') ? ' class="actif"' : ''; ?>>
            Plats et tables
        </a>
        <a href="historique.php"<?php echo ($pageCourante === 'historique') ? ' class="actif"' : ''; ?>>
            Historique<span class="badge-nb" id="badge-nb-archives" style="display:none;"></span>
        </a>
        <a href="parametres.php"<?php echo ($pageCourante === 'couleurs') ? ' class="actif"' : ''; ?>>
            Couleurs
        </a>
        <!-- Quitter l'admin : détruit la session puis retour au site client -->
        <a href="login.php?deconnexion=1&retour=../commande.php">
            Site client
        </a>
        <!-- Déconnexion : détruit la session et reste sur la page de connexion -->
        <a href="login.php?deconnexion=1">
            Déconnexion
        </a>
    </div>
</nav>

<script>
    /* Badges de la barre de navigation (présents sur toutes les pages admin) :
       - « commandes en cours » sur le lien Administration (nbEnCours)
       - « nouvelles validations » sur le lien Historique (maxIdArchive - dernier vu) */
    function mettreAJourBadge() {
        fetch('../api-nouvelle.php')
            .then(function (reponse) { return reponse.json(); })
            .then(function (donnees) {
                var badge = document.getElementById('badge-nb-commandes');
                if (badge) {
                    var nb = parseInt(donnees.nbEnCours, 10) || 0;
                    if (nb > 0) {
                        badge.textContent = nb;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                var badgeHist = document.getElementById('badge-nb-archives');
                if (badgeHist) {
                    var cle = 'restaurant_admin_max_archive_vu';
                    var max = parseInt(donnees.maxIdArchive, 10) || 0;
                    var vu = localStorage.getItem(cle);
                    if (vu === null) {
                        /* Première visite : les archives existantes ne comptent pas */
                        localStorage.setItem(cle, max);
                        badgeHist.style.display = 'none';
                    } else {
                        var nbNouv = max - (parseInt(vu, 10) || 0);
                        if (nbNouv > 0) {
                            badgeHist.textContent = nbNouv;
                            badgeHist.style.display = 'inline-block';
                        } else {
                            badgeHist.style.display = 'none';
                        }
                    }
                }
            })
            .catch(function () {});
    }
    mettreAJourBadge();
    setInterval(mettreAJourBadge, 8000);
</script>
