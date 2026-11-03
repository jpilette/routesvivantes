<?php
/**
 * Accès à la carte et aux fichiers qui y sont liés.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Chemin demandé, sans le dossier d’installation de WordPress.
 */
function rv_decl_request_path(): string {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path = wp_parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }

    $home = wp_parse_url(home_url('/'), PHP_URL_PATH);
    if (is_string($home) && $home !== '' && $home !== '/') {
        $prefix = untrailingslashit($home);
        if ($prefix !== '' && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
            if ($path === '') {
                $path = '/';
            }
        }
    }

    return '/' . trim($path, '/');
}

/**
 * La carte ne doit jamais partir vers l’administration WordPress.
 *
 * WordPress envoie `/admin` vers `/wp-admin/` quand la page est introuvable.
 * On retire cette redirection dès que l’adresse est celle de la carte.
 */
function rv_decl_keep_map_out_of_wp_admin(): void {
    $path = rv_decl_request_path();
    if (!str_starts_with($path, '/routesvivantes/admin')) {
        return;
    }

    remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
}

/**
 * Le formulaire public est ouvert aux visiteurs non connectés.
 *
 * On ne teste pas une capability WordPress : un anonyme n’a pas `read`.
 * Le filtre permet de fermer l’envoi sans retirer la page.
 */
function rv_decl_visitor_may_submit(): bool {
    return (bool) apply_filters('rv_decl_can_submit', true);
}
