<?php
/**
 * Table, permaliens, activation.
 *
 * La désactivation régénère les permaliens et ne supprime ni la table ni les photos.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nom de la table des déclarations.
 */
function rv_decl_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'rv_declarations';
}

/**
 * Crée ou met à jour la table. dbDelta est volontairement pointilleux sur la forme SQL.
 */
function rv_decl_install_table(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = rv_decl_table_name();
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
created_at datetime NOT NULL,
status varchar(20) NOT NULL,
animal_type varchar(60) NOT NULL,
remark text NULL,
lat decimal(10,7) NOT NULL,
lng decimal(10,7) NOT NULL,
photo_1 varchar(255) NOT NULL,
photo_2 varchar(255) NOT NULL,
PRIMARY KEY  (id),
KEY created_at (created_at),
KEY status (status)
) {$charset};";

    dbDelta($sql);
    update_option('rv_decl_db_version', RV_DECL_DB_VERSION);
}

/**
 * Activation : table, dossier protégé, permaliens.
 */
function rv_decl_activate(): void {
    rv_decl_install_table();
    rv_decl_protect_storage();
    rv_decl_register_rewrites();
    flush_rewrite_rules(true);
    update_option('rv_decl_rewrite_version', RV_DECL_VERSION);
}

/**
 * Désactivation : on retire seulement les règles de réécriture.
 */
function rv_decl_deactivate(): void {
    global $wp_rewrite;

    if ($wp_rewrite instanceof WP_Rewrite) {
        foreach (array_keys(rv_decl_rewrite_map()) as $regex) {
            unset($wp_rewrite->extra_rules_top[$regex]);
        }
    }

    flush_rewrite_rules(true);
}

/**
 * Rattrape une mise à jour de schéma ou de règles sans réactiver à la main.
 */
function rv_decl_maybe_upgrade(): void {
    if (get_option('rv_decl_db_version') !== RV_DECL_DB_VERSION) {
        rv_decl_install_table();
        rv_decl_protect_storage();
    }

    if (get_option('rv_decl_rewrite_version') !== RV_DECL_VERSION) {
        update_option('rv_decl_rewrite_version', RV_DECL_VERSION);
        flush_rewrite_rules(false);
    }
}

/**
 * Avertit si les permaliens « Simple » empêchent /routesvivantes/.
 */
function rv_decl_permalink_notice(): void {
    if (!current_user_can('activate_plugins')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen === null || !in_array($screen->id, ['plugins', 'options-permalink'], true)) {
        return;
    }

    if (get_option('permalink_structure')) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html('Routes Vivantes : pour ouvrir /routesvivantes/, choisissez des permaliens autres que « Simple » dans Réglages → Permaliens, puis enregistrez.');
    echo '</p></div>';
}
