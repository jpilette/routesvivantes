<?php
/**
 * Routes hors thème : formulaire, carte, JSON, photos.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Règles, de la plus générale à la plus précise.
 *
 * Avec l’argument « top », la dernière règle ajoutée est examinée en premier.
 *
 * @return array<string, string>
 */
function rv_decl_rewrite_map(): array {
    return [
        '^routesvivantes/?$' => 'index.php?rv_decl_app=1',
        '^routesvivantes/admin/?$' => 'index.php?rv_decl_admin=1',
        '^routesvivantes/admin/donnees/?$' => 'index.php?rv_decl_json=1',
        '^routesvivantes/admin/photo/([0-9]+)/([12])/?$' => 'index.php?rv_decl_photo=1&rv_decl_id=$matches[1]&rv_decl_slot=$matches[2]',
    ];
}

/**
 * Enregistre les adresses du plugin.
 */
function rv_decl_register_rewrites(): void {
    foreach (rv_decl_rewrite_map() as $regex => $query) {
        add_rewrite_rule($regex, $query, 'top');
    }
}

/**
 * @param array<int, string> $vars
 * @return array<int, string>
 */
function rv_decl_query_vars(array $vars): array {
    $vars[] = 'rv_decl_app';
    $vars[] = 'rv_decl_admin';
    $vars[] = 'rv_decl_json';
    $vars[] = 'rv_decl_photo';
    $vars[] = 'rv_decl_id';
    $vars[] = 'rv_decl_slot';

    return $vars;
}

/**
 * Une de nos adresses est en cours de lecture.
 */
function rv_decl_is_plugin_request(): bool {
    return (bool) (
        get_query_var('rv_decl_app')
        || get_query_var('rv_decl_admin')
        || get_query_var('rv_decl_json')
        || get_query_var('rv_decl_photo')
    );
}

/**
 * Empêche WordPress de rediriger ces adresses vers une page du même nom.
 *
 * @param mixed $redirect
 * @return mixed
 */
function rv_decl_skip_canonical($redirect) {
    if (rv_decl_is_plugin_request()) {
        return false;
    }

    return $redirect;
}

/**
 * Retire la barre WordPress avant qu’elle n’enqueue ses styles.
 */
function rv_decl_hide_admin_chrome(): void {
    if (!rv_decl_is_plugin_request()) {
        return;
    }

    show_admin_bar(false);
    add_filter('show_admin_bar', '__return_false', PHP_INT_MAX);
}

/**
 * Ces pages portent un nonce ou des données privées : pas de cache pleine page.
 */
function rv_decl_mark_uncached(): void {
    if (!rv_decl_is_plugin_request()) {
        return;
    }

    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }

    nocache_headers();
}

/**
 * Choisit la réponse et empêche le thème de prendre la main.
 */
function rv_decl_dispatch(): void {
    if (get_query_var('rv_decl_photo')) {
        rv_decl_serve_photo();
        exit;
    }

    if (get_query_var('rv_decl_json')) {
        rv_decl_serve_json();
        exit;
    }

    if (get_query_var('rv_decl_admin')) {
        rv_decl_require_manager();
        rv_decl_render_document('admin');
    }

    if (get_query_var('rv_decl_app')) {
        rv_decl_render_document('app');
    }
}

/**
 * CSS et JS du formulaire uniquement.
 */
/**
 * La barre d’outils peut avoir été mise en file avant le rendu.
 */
function rv_decl_strip_wp_chrome(): void {
    $styles = ['admin-bar', 'dashicons', 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles'];
    $scripts = ['admin-bar', 'hoverintent-js'];
    foreach ($styles as $handle) {
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
    }
    foreach ($scripts as $handle) {
        wp_dequeue_script($handle);
        wp_deregister_script($handle);
    }
}

function rv_decl_enqueue_public(): void {
    rv_decl_strip_wp_chrome();
    wp_enqueue_style('rv-decl-base', RV_DECL_URL . 'assets/css/base.css', [], RV_DECL_VERSION);
    wp_enqueue_style('rv-decl-app', RV_DECL_URL . 'assets/css/app.css', ['rv-decl-base'], RV_DECL_VERSION);
    wp_enqueue_script('rv-decl-app', RV_DECL_URL . 'assets/js/app.js', [], RV_DECL_VERSION, true);
}

/**
 * CSS et JS de la carte uniquement.
 */
function rv_decl_enqueue_admin(): void {
    rv_decl_strip_wp_chrome();
    wp_enqueue_style('rv-decl-base', RV_DECL_URL . 'assets/css/base.css', [], RV_DECL_VERSION);
    wp_enqueue_style('rv-decl-leaflet', RV_DECL_URL . 'assets/vendor/leaflet/leaflet.css', [], '1.9.4');
    wp_enqueue_style(
        'rv-decl-admin',
        RV_DECL_URL . 'assets/css/admin.css',
        ['rv-decl-base', 'rv-decl-leaflet'],
        RV_DECL_VERSION
    );
    wp_enqueue_script('rv-decl-leaflet', RV_DECL_URL . 'assets/vendor/leaflet/leaflet.min.js', [], '1.9.4', true);
    wp_enqueue_script('rv-decl-admin', RV_DECL_URL . 'assets/js/admin.js', ['rv-decl-leaflet'], RV_DECL_VERSION, true);
}

/**
 * Document HTML autonome. Les styles du thème et de wp-admin ne sont pas imprimés.
 */
function rv_decl_render_document(string $template): void {
    $files = [
        'app'   => 'app.php',
        'admin' => 'admin.php',
    ];
    if (!isset($files[$template])) {
        status_header(404);
        exit;
    }

    show_admin_bar(false);
    remove_all_actions('wp_head');
    remove_all_actions('wp_footer');
    remove_all_actions('wp_enqueue_scripts');
    remove_all_actions('wp_print_styles');
    remove_all_actions('wp_print_scripts');
    remove_all_actions('wp_print_head_scripts');
    remove_all_actions('wp_print_footer_scripts');

    $enqueue = $template === 'admin' ? 'rv_decl_enqueue_admin' : 'rv_decl_enqueue_public';
    add_action('wp_enqueue_scripts', $enqueue);
    add_action('wp_head', 'wp_enqueue_scripts', 1);
    add_action('wp_head', 'wp_print_styles', 8);
    add_action('wp_head', 'wp_print_head_scripts', 9);
    // WordPress imprime les scripts de pied de page via un callback qu’on retire
    // avec les autres hooks. On appelle donc l’impression directement.
    add_action('wp_footer', 'rv_decl_print_footer_assets', 20);

    global $wp_query;
    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404 = false;
    }

    status_header(200);
    nocache_headers();
    rv_decl_send_document_headers($template);

    include RV_DECL_DIR . 'templates/' . $files[$template];
    exit;
}

/**
 * Imprime les scripts mis en file pour le pied de page, sans les scripts du thème.
 */
function rv_decl_print_footer_assets(): void {
    rv_decl_strip_wp_chrome();
    if (function_exists('print_late_styles')) {
        print_late_styles();
    }
    if (function_exists('print_footer_scripts')) {
        print_footer_scripts();
    }
}

/**
 * En-têtes communs. La carte a le droit de charger les tuiles OpenStreetMap.
 */
function rv_decl_send_document_headers(string $template): void {
    $img = "'self' blob: data:";
    if ($template === 'admin') {
        $img .= ' https://tile.openstreetmap.org https://*.tile.openstreetmap.org';
    }

    $policy = "default-src 'self'; img-src {$img}; media-src 'self' blob:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'";

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Content-Security-Policy: ' . $policy);

    if ($template === 'admin') {
        header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
        header('X-Robots-Tag: noindex, nofollow');
        return;
    }

    header('Permissions-Policy: camera=(self), geolocation=(self), microphone=()');
}
