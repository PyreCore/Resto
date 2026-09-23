# Resto

Système de gestion de commandes de restaurant en **PHP + MySQL**.

## Dossier

Le projet complet se trouve dans [`Restaurant/`](Restaurant/README.md) :

- Prise de commande (table, menu, prix)
- Administration : plats, tables, paramètres, tickets
- Historique et archivage des commandes
- Sauvegarde de la base
- Sécurité Apache (`.htaccess`) et PHP (`config.php`)

## Technologies

- PHP
- MySQL (fichier `Restaurant/database.sql`)
- HTML / CSS / JavaScript

## Démarrage

1. Importer `Restaurant/database.sql` dans MySQL (base `restaurant`).
2. Configurer les identifiants dans `Restaurant/config.php`.
3. Servir le dossier via un serveur web avec PHP (Apache recommandé).

## License

[MIT](LICENSE)