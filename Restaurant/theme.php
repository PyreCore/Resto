<?php
/* ============================================
   theme.php - Couleurs du site (LeDélise)
   Charge les couleurs enregistrées dans la table « parametres »
   et fournit le <style> qui redéfinit les variables CSS du thème.
   Les couleurs sont modifiables depuis admin/parametres.php.
   ============================================ */

require_once __DIR__ . '/config.php';

/* Valeurs par défaut du thème « Brun chaleureux » */
function couleurs_theme() {
    static $couleurs = null;
    if ($couleurs !== null) {
        return $couleurs;
    }
    $couleurs = array(
        'couleur_fond'         => '#2B1D12',
        'couleur_principale'   => '#A54A12',
        'couleur_accent'       => '#C05621',
        'couleur_accent_fonce' => '#7C3E12',
        'couleur_accent_clair' => '#E0A56B',
    );
    try {
        $bdd = connexion_bdd();
        foreach ($bdd->query('SELECT cle, valeur FROM parametres') as $ligne) {
            if (isset($couleurs[$ligne['cle']])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $ligne['valeur'])) {
                $couleurs[$ligne['cle']] = strtoupper($ligne['valeur']);
            }
        }
        $bdd = null;
    } catch (Exception $e) {
        /* Base inaccessible : les valeurs par défaut sont conservées */
    }
    return $couleurs;
}

/* Éclaircit une couleur hexadécimale (#RRGGBB) d'un pourcentage (0 à 1) */
function eclaicir_couleur($hex, $pourcent) {
    $hex = ltrim($hex, '#');
    $r = (int)round(hexdec(substr($hex, 0, 2)) + (255 - hexdec(substr($hex, 0, 2))) * $pourcent);
    $g = (int)round(hexdec(substr($hex, 2, 2)) + (255 - hexdec(substr($hex, 2, 2))) * $pourcent);
    $b = (int)round(hexdec(substr($hex, 4, 2)) + (255 - hexdec(substr($hex, 4, 2))) * $pourcent);
    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

/* <style> qui applique les couleurs enregistrées via des variables CSS */
function style_theme() {
    $c = couleurs_theme();
    $nav = $c['couleur_fond'] . 'F2'; /* fond à ~95 % pour la barre de navigation */
    return '<style>'
         . ':root{'
         . '--c-fond:' . $c['couleur_fond'] . ';'
         . '--c-fond-nav:' . $nav . ';'
         . '--c-principale:' . $c['couleur_principale'] . ';'
         . '--c-accent:' . $c['couleur_accent'] . ';'
         . '--c-accent-fonce:' . $c['couleur_accent_fonce'] . ';'
         . '--c-accent-clair:' . $c['couleur_accent_clair'] . ';'
         . '--c-fond-tres-clair:' . eclaicir_couleur($c['couleur_principale'], 0.88) . ';'
         . '--c-fond-clair:' . eclaicir_couleur($c['couleur_principale'], 0.78) . ';'
         . '--c-fond-clair-hover:' . eclaicir_couleur($c['couleur_principale'], 0.72) . ';'
         . '--c-bordure:' . eclaicir_couleur($c['couleur_principale'], 0.60) . ';'
         . '}'
         . '</style>';
}
