<?php require_once __DIR__ . '/config.php'; $pageCourante = 'commande'; include 'theme.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=25">
    <?php echo style_theme(); ?>
    <title>Nouvelle commande - LeDélise</title>
</head>

<body class="page-formulaire">

<div class="fond"></div>

<?php include 'navbar.php'; ?>

<div class="container page-commande">

    <h1>Nouvelle commande</h1>

    <div class="layout-commande">

        <aside class="lateral">
            <p class="note-lateral">
                Une nouvelle commande peut être supprimée à tout moment depuis l'administration.
            </p>
        </aside>

        <div class="formulaire">

            <?php
            /* Chargement du menu (plats par catégorie avec prix) et des tables disponibles */
            $plats = array();
            $tables = array();
            try {
                $bddMenu = connexion_bdd();
                foreach ($bddMenu->query("SELECT * FROM plats ORDER BY categorie, nom") as $plat) {
                    $plats[$plat['categorie']][$plat['nom']] = (int)$plat['prix'];
                }
                foreach ($bddMenu->query("SELECT numero FROM tables ORDER BY numero") as $t) {
                    $tables[] = (int)$t['numero'];
                }
                $bddMenu = null;
            } catch (Exception $e) {
                // En cas de panne, le menu et les tables restent vides.
            }
            ?>

            <form action="commandes-ajout.php" method="post" class="formulaire-commande">

                <div class="grille-commande">

                    <fieldset>
                        <label for="type_commande">Type de commande</label>
                        <select id="type_commande" name="type_commande" required>
                            <option value="sur_place" selected>Sur place</option>
                            <option value="emporter">Emporter</option>
                        </select>
                    </fieldset>

                    <fieldset id="champ-table">
                        <label for="numero_table">Numéro de la table</label>
                        <select id="numero_table" name="numero_table">
                            <option value="" selected>Choisir une table</option>
                            <?php foreach ($tables as $t): ?>
                            <option value="<?php echo $t; ?>">Table n° <?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>

                    <fieldset>
                        <label for="nom_client">Nom du client</label>
                        <input
                            id="nom_client"
                            name="nom_client"
                            type="text"
                            placeholder="Facultatif"
                        />
                    </fieldset>

                    <fieldset>
                        <label for="telephone">Téléphone</label>
                        <input
                            id="telephone"
                            name="telephone"
                            type="tel"
                            placeholder="+24165005000"
                            required
                        />
                    </fieldset>

                    <fieldset class="champ-plein">
                        <label for="menu">MENU</label>
                        <select id="menu" name="menu">
                            <option value="" selected>Choisir dans le menu</option>
                            <?php foreach ($plats as $categorie => $items): ?>
                            <optgroup label="<?php echo htmlspecialchars($categorie); ?>">
                                <?php foreach ($items as $nom => $prix): ?>
                                <option value="<?php echo htmlspecialchars($nom); ?>" data-prix="<?php echo $prix; ?>">
                                    <?php echo htmlspecialchars($nom); ?> (<?php echo number_format($prix, 0, ',', ' '); ?> FCFA)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>

                    <div class="champ-plein ligne-double">
                        <p id="prix" class="montant">Sélectionnez un plat pour afficher le montant</p>
                        <button type="button" id="ajouter" class="btn-secondaire">Ajouter à la corbeille</button>
                    </div>

                </div>

                <fieldset>
                    <label>CORBEILLE</label>
                    <div id="corbeille">
                        <p class="vide">Aucun plat ajouté pour le moment.</p>
                    </div>
                    <p id="total" class="total">Total : 0 FCFA</p>
                </fieldset>

                <button type="submit">Enregistrer la commande</button>

            </form>

        </div>

    </div>

    <script>
        /* Adaptation des champs selon le type de commande (sur place / emporter) */
        var type = document.getElementById('type_commande');
        var champTable = document.getElementById('champ-table');
        var tableInput = document.getElementById('numero_table');
        var nomInput = document.getElementById('nom_client');

        function miseAJourType() {
            var surPlace = type.value === 'sur_place';

            if (champTable) {
                champTable.style.display = surPlace ? '' : 'none';
                tableInput.required = surPlace;
                if (!surPlace) {
                    tableInput.value = '';
                }
            }

            if (nomInput) {
                nomInput.required = !surPlace;
                nomInput.placeholder = surPlace ? 'Facultatif' : 'Obligatoire';
            }
        }

        type.addEventListener('change', miseAJourType);
        miseAJourType();

        /* Gestion de la corbeille : ajout, affichage, retrait et total */
        var menu = document.getElementById('menu');
        var prix = document.getElementById('prix');
        var corbeille = {};

        menu.addEventListener('change', function () {
            var option = menu.options[menu.selectedIndex];
            var montant = option.getAttribute('data-prix');
            prix.textContent = montant
                ? 'Montant : ' + montant + ' FCFA'
                : 'Sélectionnez un plat pour afficher le montant';
        });

        function afficherCorbeille() {
            var zone = document.getElementById('corbeille');
            var total = 0;

            zone.innerHTML = '';

            var noms = Object.keys(corbeille);

            if (noms.length === 0) {
                zone.innerHTML = '<p class="vide">Aucun plat ajouté pour le moment.</p>';
            } else {
                noms.forEach(function (nom) {
                    var item = corbeille[nom];
                    total += item.prix * item.qte;

                    var ligne = document.createElement('div');
                    ligne.className = 'ligne-corbeille';

                    var cache = document.createElement('input');
                    cache.type = 'hidden';
                    cache.name = 'menu[]';
                    cache.value = nom;
                    ligne.appendChild(cache);

                    var cacheQte = document.createElement('input');
                    cacheQte.type = 'hidden';
                    cacheQte.name = 'qte[]';
                    cacheQte.value = item.qte;
                    ligne.appendChild(cacheQte);

                    var plat = document.createElement('span');
                    plat.className = 'plat';
                    plat.textContent = (item.qte > 1 ? item.qte + ' ' : '') + nom;
                    ligne.appendChild(plat);

                    var montant = document.createElement('span');
                    montant.className = 'prix';
                    montant.textContent = (item.prix * item.qte) + ' FCFA';
                    ligne.appendChild(montant);

                    var bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'retirer';
                    bouton.textContent = 'x';
                    bouton.addEventListener('click', function () {
                        retirer(nom);
                    });
                    ligne.appendChild(bouton);

                    zone.appendChild(ligne);
                });
            }

            document.getElementById('total').textContent = 'Total : ' + total + ' FCFA';
        }

        function retirer(nom) {
            delete corbeille[nom];
            afficherCorbeille();
        }

        document.getElementById('ajouter').addEventListener('click', function () {
            var option = menu.options[menu.selectedIndex];
            if (!option.value) {
                alert('Veuillez choisir un plat à ajouter.');
                return;
            }
            var nom = option.value;
            if (corbeille[nom]) {
                corbeille[nom].qte += 1;
            } else {
                corbeille[nom] = {
                    prix: parseInt(option.getAttribute('data-prix'), 10),
                    qte: 1
                };
            }
            afficherCorbeille();
        });
    </script>

</div>

<?php include 'footer.php'; ?>

</body>

</html>
