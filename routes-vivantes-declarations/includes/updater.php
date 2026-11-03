<?php
/**
 * Mise à jour depuis un fichier public, sans jeton et sans api.github.com.
 *
 * La version 1.1.0 interroge l’API GitHub. Un HTTP 403 (limite anonyme)
 * l’empêche de voir une release. Ce fichier n’est plus lu que par les
 * versions qui l’embarquent : il faut donc installer le zip une fois à la main.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once RV_DECL_DIR . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * JSON brut du dépôt. L’hôte n’est pas api.github.com.
 * Le champ download_url de ce fichier pointe vers le zip de la release.
 */
const RV_DECL_UPDATE_METADATA = 'https://raw.githubusercontent.com/jpilette/routesvivantes/cursor/routes-vivantes-declarations-e73d/routes-vivantes-declarations/update.json';

/**
 * @return \YahnisElsts\PluginUpdateChecker\v5p7\Plugin\UpdateChecker
 */
function rv_decl_update_checker() {
    static $checker = null;
    if ($checker === null) {
        $checker = PucFactory::buildUpdateChecker(
            RV_DECL_UPDATE_METADATA,
            RV_DECL_FILE,
            'routes-vivantes-declarations'
        );
        $checker->addQueryArgFilter('rv_decl_update_query_args');
    }
    return $checker;
}

/**
 * Le fichier brut n’a pas de paramètres. On n’ajoute pas la version PHP ni la locale.
 *
 * @param array $query_args
 * @return array
 */
function rv_decl_update_query_args($query_args) {
    unset($query_args);
    return array();
}

rv_decl_update_checker();
