<?php
/**
 * Signalements d’exemple, ajoutés seulement depuis le bouton d’administration.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_post_rv_decl_seed_examples', 'rv_decl_handle_seed_examples');

/**
 * Bouton « Ajouter 100 signalements d’exemple ». Réservé à manage_options.
 */
function rv_decl_handle_seed_examples(): void {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(esc_html('Vous ne pouvez pas ajouter ces exemples.'), '', ['response' => 403]);
    }

    check_admin_referer('rv_decl_seed_examples');

    $added = rv_decl_insert_examples();
    $args = ['page' => 'rv-decl-lieux'];
    if (is_wp_error($added)) {
        $args['rv_examples_error'] = '1';
    } else {
        $args['rv_examples'] = (string) $added;
    }

    wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    exit;
}

/**
 * Insère 100 signalements d’exemple. Ne supprime aucune ligne existante.
 *
 * @return int|\WP_Error Nombre ajouté, ou une erreur.
 */
function rv_decl_insert_examples() {
    if (!current_user_can('manage_options')) {
        return new WP_Error('rv_examples', 'Vous ne pouvez pas ajouter ces exemples.');
    }

    if (function_exists('set_time_limit')) {
        set_time_limit(180);
    }
    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $places = rv_decl_example_places();
    $animals = array_keys(rv_decl_animals());
    $remarks = rv_decl_example_remarks();
    if (count($places) < 100 || $animals === []) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $now = new DateTimeImmutable('now', $timezone);
    $created_files = [];

    global $wpdb;
    $wpdb->query('START TRANSACTION');

    for ($i = 0; $i < 100; $i++) {
        $place = $places[$i];
        $animal = $animals[$i % count($animals)];
        $status = ($i % 3 === 0) ? 'blessé' : 'mort';
        $remark = 'Exemple. ' . $remarks[$i % count($remarks)];
        $created = $now->modify('-' . (2 + ($i * 4)) . ' days')->setTime(6 + ($i % 14), ($i * 7) % 60, ($i * 11) % 60);
        $mysql = $created->format('Y-m-d H:i:s');

        $photo_1 = rv_decl_store_example_photo($animal, 1, $mysql);
        if (is_wp_error($photo_1)) {
            $wpdb->query('ROLLBACK');
            rv_decl_delete_example_files($created_files);
            return $photo_1;
        }
        $created_files[] = $photo_1;

        $photo_2 = rv_decl_store_example_photo($animal, 2, $mysql);
        if (is_wp_error($photo_2)) {
            $wpdb->query('ROLLBACK');
            rv_decl_delete_example_files($created_files);
            return $photo_2;
        }
        $created_files[] = $photo_2;

        $inserted = $wpdb->insert(
            rv_decl_table_name(),
            [
                'created_at'  => $mysql,
                'status'      => $status,
                'animal_type' => $animal,
                'remark'      => $remark,
                'lat'         => sprintf('%.7F', $place[0]),
                'lng'         => sprintf('%.7F', $place[1]),
                'photo_1'     => $photo_1,
                'photo_2'     => $photo_2,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        if ($inserted === false) {
            $wpdb->query('ROLLBACK');
            rv_decl_delete_example_files($created_files);
            return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
        }
    }

    $wpdb->query('COMMIT');

    return 100;
}

/**
 * @param array<int, string> $files
 */
function rv_decl_delete_example_files(array $files): void {
    foreach ($files as $relative) {
        rv_decl_delete_photo($relative);
    }
}

/**
 * JPEG simple, rangé comme une photo réelle.
 *
 * @return string|\WP_Error Chemin relatif à basedir.
 */
function rv_decl_store_example_photo(string $animal, int $slot, string $mysql) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    $animals = rv_decl_animals();
    $label = $animals[$animal]['label'] ?? $animal;
    $image = imagecreatetruecolor(480, 320);
    if ($image === false) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    $palette = [
        [47, 79, 63],
        [90, 110, 72],
        [120, 96, 58],
        [62, 90, 110],
        [92, 70, 70],
    ];
    $tone = $palette[(strlen($animal) + $slot) % count($palette)];
    if ($slot === 2) {
        $tone = [$tone[2], $tone[0], $tone[1]];
    }
    $background = imagecolorallocate($image, $tone[0], $tone[1], $tone[2]);
    $ink = imagecolorallocate($image, 244, 241, 232);
    imagefilledrectangle($image, 0, 0, 479, 319, $background);
    imagefilledellipse($image, 240, 150, 160, 160, $ink);
    imageellipse($image, 240, 150, 200, 200, $ink);

    $line = 'Exemple ' . $slot;
    $name = function_exists('iconv') ? iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $label) : $label;
    if (!is_string($name) || $name === '') {
        $name = 'Animal';
    }
    imagestring($image, 5, 24, 24, $line, $ink);
    imagestring($image, 5, 24, 260, $name, $ink);

    $relative = rv_decl_example_photo_path($mysql);
    if (is_wp_error($relative)) {
        imagedestroy($image);
        return $relative;
    }

    $uploads = wp_upload_dir();
    $absolute = wp_normalize_path(trailingslashit($uploads['basedir']) . $relative);
    $written = imagejpeg($image, $absolute, 82);
    imagedestroy($image);
    if (!$written) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    $checked = rv_decl_absolute_from_relative($relative);
    $info = $checked !== null ? @getimagesize($checked) : false;
    if ($checked === null || !is_array($info) || ($info['mime'] ?? '') !== 'image/jpeg') {
        if (is_file($absolute)) {
            wp_delete_file($absolute);
        }
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    @chmod($checked, 0644);

    return $relative;
}

/**
 * Chemin relatif, même motif que les photos envoyées.
 *
 * @return string|\WP_Error
 */
function rv_decl_example_photo_path(string $mysql) {
    if (!preg_match('/^(\d{4})-(\d{2})-\d{2} /', $mysql, $match)) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    rv_decl_protect_storage();
    $relative_dir = 'routes-vivantes-declarations/' . $match[1] . '/' . $match[2];
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    $absolute_dir = wp_normalize_path(trailingslashit($uploads['basedir']) . $relative_dir);
    if (!wp_mkdir_p($absolute_dir)) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    $index = $absolute_dir . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php\n// Silence is golden.\n");
    }

    try {
        $filename = bin2hex(random_bytes(16)) . '.jpg';
    } catch (Exception $exception) {
        return new WP_Error('rv_examples', 'Les exemples n’ont pas été ajoutés.');
    }

    return $relative_dir . '/' . $filename;
}

/**
 * Mots courts, préfixés par « Exemple. » à l’enregistrement.
 *
 * @return array<int, string>
 */
function rv_decl_example_remarks(): array {
    return [
        'Sur le bas-côté.',
        'Près du fossé.',
        'Au carrefour.',
        'Après le virage.',
        'Sur la bande d’arrêt.',
        'Près du pont.',
        'En lisière du bois.',
        'Devant le champ.',
        'Au bord de la départementale.',
        'Sous les arbres.',
    ];
}

/**
 * Centres de communes, sur terre, répartis en France métropolitaine.
 *
 * @return array<int, array{0: float, 1: float}>
 */
function rv_decl_example_places(): array {
    $rows = [
        [50.6292, 3.0573],
        [49.8942, 2.2957],
        [49.4431, 1.0993],
        [49.1829, -0.3707],
        [48.1173, -1.6778],
        [48.3904, -4.4861],
        [47.9960, -4.0978],
        [47.6582, -2.7608],
        [48.5140, -2.7653],
        [47.2184, -1.5536],
        [47.4784, -0.5632],
        [48.0061, 0.1996],
        [47.3941, 0.6848],
        [47.9029, 1.9090],
        [47.5861, 1.3359],
        [48.4439, 1.4881],
        [48.8566, 2.3522],
        [48.5406, 2.6596],
        [49.4295, 2.0807],
        [49.4178, 2.8261],
        [49.2583, 4.0317],
        [48.2973, 4.0744],
        [48.9562, 4.3634],
        [49.7736, 4.7196],
        [49.1193, 6.1757],
        [48.6921, 6.1844],
        [48.5734, 7.7521],
        [48.0794, 7.3585],
        [47.7508, 7.3359],
        [48.1728, 6.4510],
        [47.2378, 6.0241],
        [47.3220, 5.0415],
        [47.7982, 3.5733],
        [46.9896, 3.1590],
        [47.0810, 2.3988],
        [46.8110, 1.6911],
        [46.5802, 0.3404],
        [46.3231, -0.4588],
        [46.1603, -1.1511],
        [46.6705, -1.4268],
        [45.8336, 1.2611],
        [46.1713, 1.8702],
        [45.1840, 0.7210],
        [44.8378, -0.5792],
        [44.2031, 0.6164],
        [44.0176, 1.3542],
        [43.6047, 1.4442],
        [43.9280, 2.1480],
        [44.3494, 2.5750],
        [44.4475, 1.4412],
        [43.6465, 0.5855],
        [43.2951, -0.3708],
        [43.2328, 0.0781],
        [43.4929, -1.4748],
        [43.8902, -0.4996],
        [45.6484, 0.1562],
        [44.8520, 0.4830],
        [45.7460, -0.6330],
        [48.0698, -0.7700],
        [48.4306, 0.0931],
        [49.0241, 1.1508],
        [49.9228, 1.0775],
        [50.1054, 1.8358],
        [50.2910, 2.7772],
        [50.3571, 3.5232],
        [51.0343, 2.3768],
        [50.9513, 1.8587],
        [50.7264, 1.6147],
        [49.6398, -1.6164],
        [49.1156, -1.0913],
        [48.3517, -1.1996],
        [48.1243, -1.2097],
        [47.6518, -2.0848],
        [48.0686, -2.9670],
        [47.7482, -3.3667],
        [47.6686, -2.9816],
        [47.0600, -0.8780],
        [47.2600, -0.0769],
        [47.1667, 0.2431],
        [47.7934, 1.0656],
        [47.9966, 2.7326],
        [48.1974, 3.2833],
        [48.5600, 3.2994],
        [48.4047, 2.7016],
        [48.9606, 2.8883],
        [49.3817, 3.3236],
        [49.5641, 3.6243],
        [49.8480, 3.2874],
        [49.1596, 5.3840],
        [48.7736, 5.1610],
        [48.1110, 5.1389],
        [47.6226, 6.1557],
        [47.6379, 6.8628],
        [46.6754, 5.5544],
        [46.3069, 4.8287],
        [45.7640, 4.8357],
        [45.4397, 4.3872],
        [45.7772, 3.0870],
        [45.0431, 3.8851],
        [44.9280, 2.4403],
    ];

    $places = [];
    foreach ($rows as $row) {
        $places[] = [(float) $row[0], (float) $row[1]];
    }

    return $places;
}
