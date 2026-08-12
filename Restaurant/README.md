# Restaurant (LeDélise)

Système de gestion de commandes de restaurant en PHP + MySQL.

## Fichiers

| Fichier | Rôle |
| --- | --- |
| `database.sql` | Configuration de la base : création de `restaurant` et des tables `commandes`, `commandes_items`, `commandes_archivees`, `commandes_archivees_items`, `plats`, `tables`, `parametres` |
| `config.php` | **Configuration centrale** : identifiants de connexion à la base, fuseau horaire, gestion des erreurs (journalisation), mot de passe administrateur, fonctions `connexion_bdd()` et `demarrer_session()` |
| `index.php` | Page d'accueil : redirige vers `commande.php` |
| `.htaccess` | Sécurité Apache : masque la liste des fichiers (`Options -Indexes`), définit `index.php` comme page par défaut, bloque l'accès direct à `config.php`, `database.sql`, `logs/`, `backups/`, `scripts/` |
| `commande.php` | Formulaire de nouvelle commande (corbeille, menu, prix) |
| `commandes-ajout.php` | Traite l'envoi du formulaire et insère la commande en base |
| `commandes-liste.php` | Redirige vers l'administration (`admin/index.php`) |
| `api-nouvelle.php` | Mini-API JSON : `maxId`, `maxIdArchive`, `nbEnCours` pour les notifications de l'admin (nouvelles commandes, validations, badge) |
| `admin/index.php` | **Administration** : statistiques, filtres, cartes des commandes par ticket, validation/annulation des commandes (`localhost/Restaurant/admin`) |
| `admin/login.php` | Page de connexion à l'administration (mot de passe défini dans `config.php`, anti force-brute) |
| `admin/protection.php` | Vérifie la session admin ; redirige vers `login.php` si non connecté |
| `admin/historique.php` | **Historique** : commandes validées et archivées, triées par date de validation |
| `admin/navbar.php` | Barre de navigation de l'administration |
| `admin/parametres.php` | **Couleurs** : thèmes prédéfinis ou couleurs personnalisées, appliquées à tout le site |
| `admin/ticket.php` | **Ticket de commande** : aperçu et impression d'un ticket de caisse compact (~80 mm) pour une commande en cours (`admin/ticket.php?id=N`) |
| `theme.php` | Charge les couleurs de la table `parametres` et les applique via des variables CSS |
| `navbar.php` | Barre de navigation du site client (nouvelle commande, mes commandes) |
| `mes-commandes.php` | **Mes commandes** : suivi des commandes d'un client par numéro de téléphone (commandes en cours + validées) |
| `footer.php` | Pied de page (partagé) |
| `style.css` | Feuille de style du site |
| `scripts/sauvegarde.php` | Script de sauvegarde de la base (ligne de commande uniquement) |
| `sauvegarde.bat` | Lance la sauvegarde via PHP (double-clic sous Windows) |
| `logs/` | Journal des erreurs PHP (`erreurs.log`, créé par `config.php`) — inaccessible depuis le navigateur |
| `backups/` | Sauvegardes SQL générées par `scripts/sauvegarde.php` — inaccessible depuis le navigateur |
| `.gitignore` | Fichiers à ne pas versionner (`logs/`, `backups/`, `config.php`, `.idea/`) |
| `image/` | Dossier des images |

## Fonctionnement

### Base de données

- Base : `restaurant`
- Table : `commandes` (une ligne = **une commande** d'un client)
- Table : `commandes_items` (une ligne = **un plat** commandé, relié à sa commande)
- Table : `commandes_archivees` (commandes validées, déplacées hors de la liste globale)
- Table : `commandes_archivees_items` (plats des commandes validées et archivées)
- Table : `tables` (numéros des tables de la salle, gérés depuis l'administration)
- Table : `parametres` (couleurs du thème du site, gérées depuis `admin/parametres.php`)
- Champs (`commandes`) : `id`, `type_commande`, `numero_table`, `nom_client`, `telephone`, `date`, `statut`, `total`
- Champs (`commandes_items`) : `id`, `commande_id` (lien vers `commandes`), `menu`, `prix`, `quantite`
- Champs (`commandes_archivees`) : `id` (auto-généré), `commande_origine` (numéro de la commande d'origine), `type_commande`, `numero_table`, `nom_client`, `telephone`, `date`, `date_validation`, `total`
- Champs (`commandes_archivees_items`) : `id`, `archive_id` (lien vers `commandes_archivees`), `menu`, `prix`, `quantite`
- Champs (`tables`) : `id`, `numero` (unique)
- Champs (`parametres`) : `id`, `cle` (unique), `valeur` — une ligne par paramètre
- Connexion : centralisée dans `config.php` (fonction `connexion_bdd()`, encodage UTF-8, requêtes préparées côté serveur)

### Configuration

Toute la configuration du site est regroupée dans **`config.php`** :

- **Base de données** : `BDD_HOTE`, `BDD_NOM`, `BDD_UTILISATEUR`, `BDD_MOT_DE_PASSE`.
- **Administration** : `ADMIN_MOT_DE_PASSE` (mot de passe unique, à changer en production) et `ADMIN_EXPIRATION` (durée de la session admin, 300 s = 5 min).
- **Fuseau horaire** : `FUSEAU_HORAIRE` (`Africa/Abidjan` par défaut).
- **Erreurs** : en production (`AFFICHER_ERREURS = false`) les erreurs PHP ne sont **jamais affichées** au visiteur ; elles sont journalisées dans `logs/erreurs.log` (dossier créé automatiquement).
- **Fonctions** : `connexion_bdd()` (connexion PDO unique : exceptions, tableau associatif, UTF-8, requêtes préparées côté serveur) et `demarrer_session()` (session avec cookies `HttpOnly` + `SameSite=Lax`).

Ce fichier contient les identifiants de connexion : il est inaccessible depuis le navigateur (bloqué par `.htaccess`) et n'est pas versionné (voir `.gitignore`).

### Sauvegarde et restauration

Une sauvegarde complète de la base peut être générée à tout moment :

- **Double-clic sur `sauvegarde.bat`** (Windows) — ou en ligne de commande : `php scripts/sauvegarde.php`.
- Le fichier `backups/sauvegarde_AAAA-MM-JJ_HHMMSS.sql` contient toute la base (structure + données) et est **restaurable tel quel** (import dans phpMyAdmin, ou `mysql -u root restaurant < backups\sauvegarde_....sql`).
- Les sauvegardes de plus de **30 jours** sont supprimées automatiquement (`NB_JOURS` dans `scripts/sauvegarde.php`).
- Pour fiabiliser, pensez à lancer la sauvegarde **régulièrement** (ou planifiez-la) — voir « Importance des données ».

### Sécurité

- **Connexion admin** : mot de passe vérifié par comparaison à temps constant, **anti force-brute** (blocage temporaire de 5 minutes après 5 échecs successifs, géré dans `admin/login.php`), identifiant de session renouvelé à la connexion (`session_regenerate_id`).
- **Sessions** : cookies `HttpOnly` (illisibles par JavaScript) et `SameSite=Lax` (non envoyés depuis un autre site).
- **Fichiers sensibles** : l'accès direct par le navigateur à `config.php`, `database.sql`, `*.sql`, `*.log`, `logs/`, `backups/` et `scripts/` renvoie une erreur 403 (`.htaccess`).
- **Erreurs** : masquées en production, journalisées dans `logs/erreurs.log`.

### Thème et couleurs

Les couleurs du site sont **dynamiques** et modifiables depuis l'administration (`admin/parametres.php`), avec 8 thèmes prédéfinis harmonieux (Brun chaleureux, Vert naturel, Bleu océan, Bordeaux élégant, Violet prestige, Orange épicé, Noir & or, Gris ardoise) ou un choix personnalisé (fond sombre, couleur principale, accent, accent foncé, accent clair). Les valeurs sont stockées dans la table `parametres`, chargées par `theme.php` et appliquées sur **toutes** les pages (client et admin) via des variables CSS redéfinies dans un `<style>` (fond de page, barres, boutons, titres, badges, cartes, tableaux…). Un aperçu en direct est affiché sur la page des couleurs.

### Menu et tables

Le menu et les prix (FCFA) sont stockés dans la table `plats` et gérés depuis l'administration (`admin/plats.php`). Les tables de la salle sont stockées dans la table `tables` et gérées sur la même page (ajout d'une table par numéro, retrait). Menu et tables sont chargés au moment de la commande par `commande.php` et `commandes-ajout.php`, qui récupèrent les données directement depuis la base (la validation du numéro de table vérifie qu'il existe dans `tables`).

### Notifications de l'administration

`api-nouvelle.php` renvoie en JSON trois indicateurs : `maxId` (plus grand id des commandes en cours), `maxIdArchive` (plus grand id des commandes archivées) et `nbEnCours` (nombre de commandes en cours). Ils alimentent les notifications de l'administration :
- **Tableau de bord** (`admin/index.php`) : interrogation toutes les 8 secondes (fetch) ; si `maxId` dépasse le dernier id vu (clé `localStorage` `restaurant_admin_max_id_vu`), une notification « Nouvelle(s) commande(s) reçue(s) ! » (avec le nombre) s'affiche et la page se recharge.
- **Historique** (`admin/historique.php`) : bandeau « **Récapitulatif d'aujourd'hui** » (nombre de commandes validées, total du jour, meilleure vente) ; bannière « X commande(s) en cours » avec lien vers le tableau de bord si des commandes restent à traiter ; et détection des nouvelles validations via `maxIdArchive` (clé `restaurant_admin_max_archive_vu`) → « Nouvelle commande validée ! » + rechargement.
- **Barre admin** (`admin/navbar.php`, toutes les pages) : badge rouge avec le nombre de commandes en cours sur le lien « Administration », et badge rouge avec le nombre de **nouvelles validations** (non encore consultées dans l'historique) sur le lien « Historique » ; mis à jour toutes les 8 secondes.

### Site client et administration

Le site est destiné uniquement aux clients (nouvelle commande). L'**administration** est accessible séparément sur `localhost/Restaurant/admin` : elle est protégée par un mot de passe (`config.php`, mot de passe par défaut `admin123`). Les pages `admin/index.php`, `admin/historique.php`, `admin/plats.php` et `admin/parametres.php` incluent `admin/protection.php` qui redirige vers la page de connexion si la session n'est pas valide. Un lien **Déconnexion** est présent dans la barre de navigation admin.

### Règles de commande

- Formulaire : type de commande (`sur_place`/`emporter`), numéro de table (obligatoire sur place, doit exister dans `tables`), nom du client (facultatif sur place), téléphone (toujours obligatoire).
- Une commande = une ligne dans `commandes` (avec le total) + une ligne par plat dans `commandes_items` (avec la quantité). Les doublons d'un même plat sont regroupés et les quantités additionnées.
- Requêtes préparées pour éviter les injections SQL.

## Importance des données

Ces fichiers gèrent des **données sensibles et importantes** qui ne doivent jamais être perdues :

- **Commandes clients** : historique de toutes les ventes (plats, quantités, prix), indispensable pour la comptabilité et le suivi du restaurant.
- **Données personnelles** : noms et **numéros de téléphone** des clients — données confidentielles, à manipuler avec précaution.
- **Prix et menu** : les tarifs (FCFA) définis dans le code sont la source de vérité pour le calcul des totaux et du total général.
- **Numéros de table** : servent au service en salle pour retrouver les commandes.

Conséquences d'une perte ou d'une modification accidentelle : impossibilité de connaître l'historique des ventes, perte des coordonnées clients, totaux erronés. Il faut donc **faire des sauvegardes régulières de la base `restaurant`** (voir la section « Sauvegarde et restauration », avec `sauvegarde.bat` ou `php scripts/sauvegarde.php`) et modifier le menu/les prix avec précaution.

## Journal des modifications

- **Commentaires** : tous les fichiers PHP (`index.php`, `navbar.php`, `footer.php`, `commande.php`, `commandes-ajout.php`, `commandes-liste.php`, `api-nouvelle.php`, `admin/*`) ont été commentés ligne par ligne en français ; `style.css` (commentaires `/* */` par règle), `database.sql` (commentaires `--` par instruction) et `.htaccess` (commentaires `#`) ont été commentés de la même manière.
- **Compacité** : les commentaires ligne par ligne ont été regroupés en blocs par section dans tous les fichiers (PHP, `style.css`, `database.sql`, `.htaccess`) pour rendre le code moins touffu, sans changement de comportement.
- **Sécurité** : l'administration est désormais protégée par un mot de passe (`admin/login.php`, mot de passe par défaut `admin123`). `admin/protection.php` vérifie la session sur toutes les pages admin ; lien **Déconnexion** ajouté dans la barre admin.
- **Déconnexion automatique en sortant de l'admin** : le lien « Site client » de la barre admin détruit la session et redirige vers `commande.php`. Pour revenir à l'administration, il faut resaisir le mot de passe. Un lien « Administration » (`commandes-liste.php`) a été ajouté dans la barre du site client.
- **Style du lien Administration** : dans la barre du site client, « Administration » est désormais un simple lien texte (classe `lien-texte`, sans fond de bouton), seul « Nouvelle commande » garde l'apparence de bouton.
- **Accès admin depuis le site client** : le lien « Administration » de la barre client a été supprimé. L'accès à l'administration se fait uniquement via le lien « Administration » du message de confirmation dans `commandes-ajout.php` (après l'enregistrement d'une commande).
- **Connexion** : le label « Mot de passe » de la page de connexion (`admin/login.php`) est désormais centré au milieu de son formulaire (classe `.form-login`).
- **Suppression des liens admin côté client** : pour qu'un client ne puisse pas voir les commandes, le lien « Administration » du message de confirmation (`commandes-ajout.php`) et le lien « l'administration » de la note latérale (`commande.php`) ont été retirés. L'administration n'est accessible que par l'URL directe `admin/` (mot de passe requis).
- **Fermeture automatique de la session admin** : la connexion admin expire après 5 minutes d'inactivité (`admin/protection.php`). De plus, visiter le site client (`commande.php`/`commandes-ajout.php`, via `navbar.php`) détruit la session admin : un client ne peut jamais voir les commandes sans ressaisir le mot de passe.
- **Confirmation de commande côté client** : après l'enregistrement, `commandes-ajout.php` affiche un récapitulatif de la commande (type, table, client, téléphone, plats avec prix et total) dans une carte « Votre commande », plus un lien « Passer une nouvelle commande ».
- **Nom du restaurant** : renommé « LesDelices » → « LeDélise » dans tous les fichiers (titres, logos, footer, `style.css`, `database.sql`, README).
- **Gestion des tables** : nouvelle table `tables` (id, numero unique) avec tables n° 1 à 10 par défaut. `admin/plats.php` permet désormais d'ajouter ou de retirer les tables (« Gestion des plats et des tables », lien de navigation « Plats et tables »). `commande.php` charge les tables depuis la base (au lieu du 1 à 10 codé en dur) et `commandes-ajout.php` valide la table contre la base.
- **Mise en colonnes de l'admin** : la section « Tables de la salle » de `admin/plats.php` est désormais affichée dans une colonne fixe à droite des plats (conteneur `.layout-gestion`, colonne `.colonne-droite` avec `.bloc-tables` collant) au lieu d'être en bas de page ; sur écran étroit (≤ 768 px) les deux colonnes repassent l'une sous l'autre. CSS version `v=20`.
- **Documentation PDF** : généré `Documentation_LeDelise.pdf` (12 pages) décrivant les fonctionnalités, l'architecture, la base de données (schéma complet), le contenu détaillé de chaque fichier (avec son rôle), le rôle de chaque utilisateur (client / personnel), le résumé de l'API (`api-nouvelle.php`), une définition du format JSON et les définitions SQL (`NOT NULL`, `TIMESTAMP`, `CURRENT_TIMESTAMP`).
- **Restructuration de la base** : les commandes sont désormais normalisées — `commandes` (une ligne par commande : type, table, client, téléphone, date, statut, total) + `commandes_items` (une ligne par plat : commande_id, menu, prix, quantite), et l'équivalent pour les archives (`commandes_archivees` + `commandes_archivees_items`). L'admin affiche **une carte par commande** avec la liste des plats à l'intérieur. Les 21 lignes existantes ont été migrées (regroupées en 10 commandes en cours + 3 archivées, totaux conservés). La corbeille transmet désormais les quantités (`menu[]` + `qte[]`).
- **Couleurs du site personnalisables** : nouvelle table `parametres` (cle/valeur) pour stocker les couleurs du thème. Nouveau fichier `theme.php` qui charge ces couleurs et les applique sur toutes les pages via des variables CSS redéfinies dans un `<style>` ; `style.css` a été converti en variables CSS (`--c-fond`, `--c-principale`, `--c-accent`, `--c-accent-fonce`, `--c-accent-clair` + déclinaisons claires dérivées automatiquement). Nouvelle page `admin/parametres.php` (lien « Couleurs » dans la barre admin) : **8 thèmes prédéfinis** (Brun chaleureux, Vert naturel, Bleu océan, Bordeaux élégant, Violet prestige, Orange épicé, Noir & or, Gris ardoise), couleurs personnalisées avec **aperçu en direct**, boutons Enregistrer et Réinitialiser. CSS version `v=21`.
- **Notifications de l'administration** : `api-nouvelle.php` renvoie désormais `maxId`, `maxIdArchive` et `nbEnCours`. **Tableau de bord** : la notification de nouvelle commande affiche le nombre de nouvelles commandes. **Historique** : bannière « X commande(s) en cours » avec lien vers le tableau de bord, et notification « Nouvelle commande validée ! » (interrogation toutes les 8 s, clé `localStorage` `restaurant_admin_max_archive_vu`) avec rechargement automatique. **Barre admin** (toutes les pages) : badge rouge du nombre de commandes en cours sur le lien « Administration ». Correction au passage : le total par jour de l'historique utilisait le champ inexistant `prix` au lieu de `total`. CSS version `v=22`.
- **Notifications de l'historique** : bandeau « Récapitulatif d'aujourd'hui » en haut de l'historique (nombre de commandes validées du jour, total du jour, meilleure vente du jour calculée par requête SQL `SUM(quantite)` sur les archives du jour) ; badge rouge sur le lien « Historique » de la barre admin affichant le nombre de **nouvelles validations** non encore consultées (différence entre `maxIdArchive` et la clé `localStorage` `restaurant_admin_max_archive_vu`, synchronisée à la première visite). CSS version `v=23`.
- **Correction d'encodage** : 7 fichiers (`commande.php`, `commandes-ajout.php`, `admin/login.php`, `admin/index.php`, `admin/plats.php`, `admin/parametres.php`, `admin/historique.php`) contenaient du texte doublement encodé (les accents s'affichaient « Ã© » au lieu de « é »). Tous ont été convertis en UTF-8 propre (accents corrects dans le navigateur) sans perte du contenu correct, puis vérifiés (`php -l` + test HTTP).
- **Correction du conflit d'archivage** : `admin/index.php` insérait l'id de la commande comme clé primaire de `commandes_archivees`, ce qui provoquait une erreur « Duplicata du champ » dès que `commandes` réutilisait un id déjà archivé (après restauration ou nettoyage). Désormais l'archive reçoit un `id` interne auto-incrémenté et conserve le numéro d'origine dans une nouvelle colonne `commande_origine` (remplie sur les archives existantes). L'historique affiche et recherche le numéro via `commande_origine`. Table et code (`admin/index.php`, `admin/historique.php`) mis à jour.
- **Configuration centralisée** : nouveau fichier `config.php` qui regroupe les identifiants de connexion à la base (`BDD_*`), le fuseau horaire (`FUSEAU_HORAIRE`), le mot de passe administrateur (`ADMIN_MOT_DE_PASSE`, seul endroit à modifier) et la durée de session (`ADMIN_EXPIRATION`). Il fournit `connexion_bdd()` (PDO : exceptions, tableau associatif, UTF-8, requêtes préparées côté serveur — `EMULATE_PREPARES=false`) et `demarrer_session()`. Toutes les pages utilisent désormais `connexion_bdd()` au lieu de dupliquer `new PDO(...)` (suppression de 9 connexions en dur). Les requêtes de recherche d'`admin/index.php` et `admin/historique.php` réutilisaient le même paramètre nommé `:f` : remplacé par des noms distincts (`:f1`…`/`:f5`) pour rester compatibles avec les requêtes préparées côté serveur.
- **Gestion des erreurs** : les erreurs PHP ne sont plus affichées au visiteur (production) — elles sont journalisées dans `logs/erreurs.log` (dossier créé automatiquement, paramètres `AFFICHER_ERREURS`, `error_log` dans `config.php`).
- **Durcissement des sessions** : cookies de session `HttpOnly` + `SameSite=Lax` (`demarrer_session()`), identifiant de session renouvelé à la connexion (`session_regenerate_id(true)`), expiration admin centralisée (`ADMIN_EXPIRATION`). Toutes les pages (`login.php`, `protection.php`, `navbar.php`) passent par `demarrer_session()`.
- **Anti force-brute** : la page de connexion bloque temporairement (5 minutes) après 5 mots de passe incorrects successifs ; le compteur repart de zéro après le blocage ou la connexion réussie (`admin/login.php`).
- **Sauvegarde de la base** : nouveau script `scripts/sauvegarde.php` (ligne de commande uniquement) qui exporte toute la base `restaurant` (structure + données) dans `backups/sauvegarde_AAAA-MM-JJ_HHMMSS.sql` et supprime les sauvegardes de plus de 30 jours (`NB_JOURS`). Lanceur Windows `sauvegarde.bat` (double-clic, recherche automatique de PHP).
- **Sécurité du serveur** : `.htaccess` renforcé — blocage (403) de l'accès direct à `config.php`, `database.sql`, `*.sql`, `*.log`, `*.bak`, fichiers cachés, et aux dossiers `logs/`, `backups/`, `scripts/` (avec un `.htaccess` local `Require all denied` dans chacun). Ajout de `.gitignore` (ne pas versionner `logs/`, `backups/`, `config.php`, `.idea/`).
- **Index de la base** : index ajoutés sur les colonnes fréquemment utilisées (`commandes.statut`, `commandes_items.commande_id`, `commandes_items.menu`, `commandes_archivees.commande_origine`, `commandes_archivees.date_validation`, `commandes_archivees_items.archive_id`) dans `database.sql` et appliqués à la base existante.
- **Impression de ticket** : nouvelle page `admin/ticket.php` protégée qui affiche le **ticket de caisse compact (~80 mm)** d'une commande en cours (LeDélise, date/heure, n° de commande, type, table, client, téléphone, plats avec quantités, total) avec boutons « Imprimer » (`window.print()`) et « Retour ». Bouton « Imprimer le ticket » ajouté sur chaque carte de commande du tableau de bord (`admin/index.php`, ouverture dans un nouvel onglet). Styles `.page-ticket-body`, `.ticket` et bloc `@media print` (seul le ticket est imprimé, sur fond blanc, boutons et navigation masqués). CSS version `v=24`.
- **Suivi des commandes côté client** : nouvelle page `mes-commandes.php` où le client saisit le numéro de téléphone utilisé à la commande et retrouve **toutes ses commandes** — en cours (`commandes`) et validées (`commandes_archivees`, numéro via `commande_origine`) — fusionnées, triées par date décroissante et affichées en cartes avec le statut (« En cours », « Livrée », « Annulée », « Validée »). Lien « Mes commandes » ajouté dans la barre du site client (`navbar.php`) et lien « Voir mes commandes » sur la page de confirmation (`commandes-ajout.php`).
- **Suppression de la configuration des formulaires** : la fonctionnalité « formulaires » (champs personnalisés) a été **entièrement retirée** à la demande de l'utilisateur. Fichiers `admin/formulaires.php` et `champs.php` supprimés, lien « Formulaires » retiré de `admin/navbar.php`. `commande.php` et `commandes-ajout.php` sont revenus à un formulaire **statique** (type de commande, table obligatoire sur place, nom facultatif, téléphone obligatoire), sans lecture de la table `champs`. `admin/plats.php` réutilise des libellés fixes (Catégorie, Nom du plat, Prix (FCFA)) et une catégorie toujours obligatoire. L'affichage des champs personnalisés a été supprimé du tableau de bord (`admin/index.php`), de l'historique (`admin/historique.php`), du ticket (`admin/ticket.php`) et du suivi client (`mes-commandes.php`) ; les styles dédiés retirés de `style.css`. Les tables `champs`, `commandes_champs` et `commandes_archivees_champs` ont été supprimées de `database.sql` et **droppées** de la base. CSS version `v=25` conservée.

## Conventions de travail

- **Demander la permission avant de créer un nouveau fichier.**
- **À chaque modification du projet, noter le changement dans ce README.md.**
- Langue de communication : français.
- Ne pas ajouter de commentaires dans le code sauf demande explicite.
