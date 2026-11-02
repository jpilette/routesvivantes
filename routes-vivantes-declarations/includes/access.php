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
 * Réservé aux comptes qui ont `manage_options`.
 *
 * Un visiteur anonyme part vers wp-login, puis revient sur la page demandée.
 * Un compte déjà connecté sans ce droit part vers wp-login sans `redirect_to`,
 * pour ne pas boucler avec la carte.
 */
function rv_decl_require_manager(): void {
    nocache_headers();

    if (!is_user_logged_in()) {
        auth_redirect();
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_safe_redirect(wp_login_url());
        exit;
    }
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
