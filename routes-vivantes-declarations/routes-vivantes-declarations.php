<?php
/**
 * Plugin Name:       Routes Vivantes — Déclarations
 * Plugin URI:        https://www.routesvivantes.fr/
 * Description:       Formulaire public pour déclarer un animal mort ou blessé (/routesvivantes/) et carte des signalements pour les administrateurs (/routesvivantes/admin/).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Routes Vivantes
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       routes-vivantes-declarations
 *
 * Les deux pages sont rendues hors thème. Le code est GPL. Le logo de
 * l’association n’est pas couvert par cette licence.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RV_DECL_VERSION', '1.0.0');
define('RV_DECL_DB_VERSION', '1');
define('RV_DECL_FILE', __FILE__);
define('RV_DECL_DIR', plugin_dir_path(__FILE__));
define('RV_DECL_URL', plugin_dir_url(__FILE__));
define('RV_DECL_MAX_BYTES', 8 * 1024 * 1024);
define('RV_DECL_MAX_EDGE', 2000);
define('RV_DECL_MAX_PIXELS', 24000000);
define('RV_DECL_MAX_REMARK', 500);
define('RV_DECL_RATE_LIMIT', 8);
define('RV_DECL_ATTEMPT_LIMIT', 30);

require_once RV_DECL_DIR . 'includes/animals.php';
require_once RV_DECL_DIR . 'includes/access.php';
require_once RV_DECL_DIR . 'includes/storage.php';
require_once RV_DECL_DIR . 'includes/install.php';
require_once RV_DECL_DIR . 'includes/router.php';
require_once RV_DECL_DIR . 'includes/submission.php';
require_once RV_DECL_DIR . 'includes/admin-data.php';

register_activation_hook(RV_DECL_FILE, 'rv_decl_activate');
register_deactivation_hook(RV_DECL_FILE, 'rv_decl_deactivate');

add_action('init', 'rv_decl_register_rewrites');
add_action('init', 'rv_decl_maybe_upgrade', 99);
add_filter('query_vars', 'rv_decl_query_vars');
add_filter('redirect_canonical', 'rv_decl_skip_canonical');
add_action('wp', 'rv_decl_hide_admin_chrome', 0);
add_action('wp', 'rv_decl_mark_uncached');
add_action('template_redirect', 'rv_decl_dispatch', 0);
add_action('admin_notices', 'rv_decl_permalink_notice');
add_action('wp_ajax_rv_decl_submit', 'rv_decl_handle_submit');
add_action('wp_ajax_nopriv_rv_decl_submit', 'rv_decl_handle_submit');
