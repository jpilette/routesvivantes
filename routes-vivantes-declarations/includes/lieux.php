<?php
/**
 * Écran d’administration pour déplacer le lieu d’un signalement.
 *
 * Réservé à `manage_options`. La carte publique ne modifie pas les coordonnées.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menu : Routes Vivantes. L’écran s’appelle Lieux des signalements.
 */
function rv_decl_register_lieux_menu(): void {
    add_menu_page(
        'Lieux des signalements',
        'Routes Vivantes',
        'manage_options',
        'rv-decl-lieux',
        'rv_decl_render_lieux_screen',
        'dashicons-location-alt',
        58
    );
}
add_action('admin_menu', 'rv_decl_register_lieux_menu');

/**
 * Leaflet et le script de déplacement, seulement sur cet écran.
 *
 * @param string $hook
 */
function rv_decl_enqueue_lieux_screen(string $hook): void {
    if ($hook !== 'toplevel_page_rv-decl-lieux') {
        return;
    }

    wp_enqueue_style(
        'rv-decl-leaflet',
        RV_DECL_URL . 'assets/vendor/leaflet/leaflet.css',
        [],
        '1.9.4'
    );
    wp_enqueue_style(
        'rv-decl-lieux',
        RV_DECL_URL . 'assets/css/lieux.css',
        ['rv-decl-leaflet'],
        RV_DECL_VERSION
    );
    wp_enqueue_script(
        'rv-decl-leaflet',
        RV_DECL_URL . 'assets/vendor/leaflet/leaflet.min.js',
        [],
        '1.9.4',
        true
    );
    wp_enqueue_script(
        'rv-decl-lieux',
        RV_DECL_URL . 'assets/js/lieux.js',
        ['rv-decl-leaflet'],
        RV_DECL_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'rv_decl_enqueue_lieux_screen');

/**
 * Page du menu. La liste est rendue côté serveur. La carte est dans le script.
 */
function rv_decl_render_lieux_screen(): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html('Vous ne pouvez pas modifier ces signalements.'));
    }

    $rows = rv_decl_query_declarations(null, null);
    $added = isset($_GET['rv_examples']) ? absint(wp_unslash($_GET['rv_examples'])) : 0;
    $seed_failed = isset($_GET['rv_examples_error']);
    ?>
    <div class="wrap" id="rv-lieux" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('rv_decl_move')); ?>">
        <h1>Lieux des signalements</h1>
        <?php if ($added > 0) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html(sprintf('%d signalements d’exemple ont été ajoutés. Aucun signalement déjà présent n’a été retiré.', $added)); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($seed_failed) : ?>
            <div class="notice notice-error">
                <p>Les exemples n’ont pas été ajoutés. Réessayez.</p>
            </div>
        <?php endif; ?>
        <p>Choisissez un signalement. Déplacez le point sur la carte, puis enregistrez le lieu.</p>
        <section class="rv-examples" aria-labelledby="rv-examples-title">
            <h2 id="rv-examples-title">Signalements d’exemple</h2>
            <p>Ce bouton ajoute 100 signalements d’exemple, répartis en France, avec des photos créées pour l’essai. Il ne s’exécute que lorsque vous le demandez. Il ne retire pas les signalements déjà enregistrés. Vous pouvez le refaire : cela ajoute encore des exemples.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('rv_decl_seed_examples'); ?>
                <input type="hidden" name="action" value="rv_decl_seed_examples">
                <button type="submit" class="button">Ajouter 100 signalements d’exemple</button>
            </form>
        </section>
        <?php if (!$rows) : ?>
            <p>Aucun signalement pour le moment.</p>
        <?php else : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Animal</th>
                        <th scope="col">État</th>
                        <th scope="col">Latitude</th>
                        <th scope="col">Longitude</th>
                        <th scope="col">Lieu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $item = rv_decl_present_declaration($row);
                        $id = (int) $item['id'];
                        $label = trim((string) $item['animal_label'] . ', ' . (string) $item['status_label'], ', ');
                        ?>
                        <tr id="rv-row-<?php echo esc_attr((string) $id); ?>">
                            <td><?php echo esc_html((string) $item['date_label']); ?></td>
                            <td><?php echo esc_html((string) $item['animal_label']); ?></td>
                            <td><?php echo esc_html((string) $item['status_label']); ?></td>
                            <td class="rv-lat"><?php echo esc_html(rv_decl_format_coord((float) $item['lat'])); ?></td>
                            <td class="rv-lng"><?php echo esc_html(rv_decl_format_coord((float) $item['lng'])); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="button rv-pick"
                                    data-id="<?php echo esc_attr((string) $id); ?>"
                                    data-lat="<?php echo esc_attr(sprintf('%.7F', (float) $item['lat'])); ?>"
                                    data-lng="<?php echo esc_attr(sprintf('%.7F', (float) $item['lng'])); ?>"
                                    data-label="<?php echo esc_attr($label); ?>"
                                >Déplacer ce lieu</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <section class="rv-editor" id="rv-editor" hidden aria-labelledby="rv-editor-title">
                <h2 id="rv-editor-title">Déplacez le lieu</h2>
                <p id="rv-editor-help">Le point est sur le lieu du signalement. Déplacez-le, puis enregistrez.</p>
                <div id="rv-move-map" role="region" aria-label="Carte pour déplacer le lieu"></div>
                <p>Latitude <strong id="rv-editor-lat"></strong></p>
                <p>Longitude <strong id="rv-editor-lng"></strong></p>
                <p>
                    <button type="button" class="button button-primary" id="rv-move-save">Enregistrer ce lieu</button>
                </p>
                <p class="rv-move-status" id="rv-move-status" role="status" hidden></p>
            </section>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Coordonnée affichée avec une virgule, sur 5 décimales.
 */
function rv_decl_format_coord(float $value): string {
    return number_format($value, 5, ',', '');
}
