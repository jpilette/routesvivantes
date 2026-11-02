<?php
/**
 * Mise à jour depuis le dépôt GitHub public, sans jeton.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once RV_DECL_DIR . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Branche qui porte les versions publiées.
 * Les releases et les tags sont consultés avant cette branche
 * lorsque son nom est main (comportement de Plugin Update Checker).
 */
const RV_DECL_UPDATE_BRANCH = 'main';

const RV_DECL_UPDATE_REPO = 'https://github.com/jpilette/routesvivantes';

/**
 * @return \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\PluginUpdateChecker
 */
function rv_decl_update_checker() {
    static $checker = null;
    if ($checker === null) {
        $checker = PucFactory::buildUpdateChecker(
            RV_DECL_UPDATE_REPO,
            RV_DECL_FILE,
            'routes-vivantes-declarations'
        );
        $checker->setBranch(RV_DECL_UPDATE_BRANCH);
        $checker->getVcsApi()->enableReleaseAssets('/\.zip($|[?&#])/i');
    }
    return $checker;
}

/**
 * Le fichier principal et readme.txt sont dans le dossier du plugin,
 * pas à la racine du dépôt. La bibliothèque les demande à la racine.
 *
 * @param mixed  $preempt
 * @param array  $args
 * @param string $url
 * @return mixed
 */
function rv_decl_redirect_plugin_metadata($preempt, $args, $url) {
    if ($preempt !== false) {
        return $preempt;
    }
    $rewritten = rv_decl_plugin_metadata_url($url);
    if ($rewritten === null) {
        return false;
    }
    remove_filter('pre_http_request', 'rv_decl_redirect_plugin_metadata', 10);
    $response = wp_remote_get($rewritten, $args);
    add_filter('pre_http_request', 'rv_decl_redirect_plugin_metadata', 10, 3);
    return $response;
}

/**
 * @param string $url
 * @return string|null
 */
function rv_decl_plugin_metadata_url($url) {
    if (!is_string($url) || $url === '') {
        return null;
    }
    $pattern = '#^https://api\.github\.com/repos/jpilette/routesvivantes/contents/(routes-vivantes-declarations\.php|readme\.txt)(\?.*)?$#';
    if (!preg_match($pattern, $url, $matches)) {
        return null;
    }
    $query = isset($matches[2]) ? $matches[2] : '';
    return 'https://api.github.com/repos/jpilette/routesvivantes/contents/routes-vivantes-declarations/' . $matches[1] . $query;
}

/**
 * L’archive du dépôt contient le dossier du plugin à l’intérieur.
 * WordPress doit installer ce dossier, pas la racine du dépôt.
 *
 * @param string $source
 * @param string $remote_source
 * @param object $upgrader
 * @param array  $hook_extra
 * @return string
 */
function rv_decl_select_plugin_directory($source, $remote_source, $upgrader, $hook_extra = array()) {
    unset($remote_source, $upgrader);
    if (!is_string($source) || $source === '') {
        return $source;
    }
    $basename = plugin_basename(RV_DECL_FILE);
    if (is_array($hook_extra) && isset($hook_extra['plugin']) && $hook_extra['plugin'] !== $basename) {
        return $source;
    }
    $plugin_root = rv_decl_find_bundled_plugin_root($source);
    if ($plugin_root === '') {
        return $source;
    }
    return trailingslashit($plugin_root);
}

/**
 * @param string $source
 * @return string
 */
function rv_decl_find_bundled_plugin_root($source) {
    $source = rtrim($source, "/\\");
    if ($source === '' || !is_dir($source)) {
        return '';
    }
    $candidates = array(
        $source,
        $source . '/routes-vivantes-declarations',
    );
    foreach ($candidates as $candidate) {
        if (is_file($candidate . '/routes-vivantes-declarations.php')) {
            return $candidate;
        }
    }
    return '';
}

rv_decl_update_checker();
add_filter('pre_http_request', 'rv_decl_redirect_plugin_metadata', 10, 3);
add_filter('upgrader_source_selection', 'rv_decl_select_plugin_directory', 20, 4);
