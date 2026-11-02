<?php
/**
 * Réception du formulaire public.
 *
 * Le nonce suffit pour un visiteur anonyme. Le débit et la taille limitent les abus.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Point d’entrée admin-ajax, connecté ou non.
 */
function rv_decl_handle_submit(): void {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        rv_decl_send_error('L’envoi n’a pas marché. Rechargez la page.', 405);
    }

    if (!rv_decl_visitor_may_submit()) {
        rv_decl_send_error('Le formulaire est fermé.', 403);
    }

    $nonce = '';
    if (isset($_POST['nonce']) && !is_array($_POST['nonce'])) {
        $nonce = sanitize_text_field(wp_unslash((string) $_POST['nonce']));
    }
    if ($nonce === '' || !wp_verify_nonce($nonce, 'rv_decl_submit')) {
        rv_decl_send_error('La page est trop ancienne. Rechargez la page.', 403);
    }

    if (!rv_decl_rate_allow('attempt', RV_DECL_ATTEMPT_LIMIT)) {
        rv_decl_send_error('Trop de signalements. Attendez une heure. Puis réessayez.', 429);
    }

    $status = rv_decl_post_text('status');
    $animal = rv_decl_post_text('animal_type');
    $remark = rv_decl_post_remark();
    $statuses = rv_decl_statuses();
    $animals = rv_decl_animals();

    if (!isset($statuses[$status])) {
        rv_decl_send_error('Dites si l’animal est mort ou blessé.');
    }
    if (!isset($animals[$animal])) {
        rv_decl_send_error('Choisissez un animal de la liste.');
    }

    $remark_length = function_exists('mb_strlen') ? mb_strlen($remark) : strlen($remark);
    if ($remark_length > RV_DECL_MAX_REMARK) {
        rv_decl_send_error('Le mot est trop long. 500 signes maximum.');
    }

    $lat = rv_decl_parse_coord(rv_decl_post_text('lat'), -90.0, 90.0);
    $lng = rv_decl_parse_coord(rv_decl_post_text('lng'), -180.0, 180.0);
    if ($lat === null || $lng === null) {
        rv_decl_send_error('Le lieu est obligatoire. Autorisez la position du téléphone.');
    }
    if (abs((float) $lat) < 0.0000001 && abs((float) $lng) < 0.0000001) {
        rv_decl_send_error('Le lieu est obligatoire. Autorisez la position du téléphone.');
    }

    if (!rv_decl_rate_allow('success', RV_DECL_RATE_LIMIT, false)) {
        rv_decl_send_error('Trop de signalements. Attendez une heure. Puis réessayez.', 429);
    }

    $photo_1_file = rv_decl_optional_upload('photo_1');
    if ($photo_1_file === null) {
        rv_decl_send_error('La photo 1 est obligatoire.');
    }
    $photo_1 = rv_decl_store_uploaded_photo($photo_1_file);
    if (is_wp_error($photo_1)) {
        rv_decl_send_error($photo_1->get_error_message());
    }

    $photo_2 = '';
    $photo_2_file = rv_decl_optional_upload('photo_2');
    if ($photo_2_file !== null) {
        $photo_2 = rv_decl_store_uploaded_photo($photo_2_file);
        if (is_wp_error($photo_2)) {
            rv_decl_delete_photo($photo_1);
            rv_decl_send_error($photo_2->get_error_message());
        }
    }

    global $wpdb;
    $inserted = $wpdb->insert(
        rv_decl_table_name(),
        [
            'created_at'  => current_time('mysql'),
            'status'      => $status,
            'animal_type' => $animal,
            'remark'      => $remark,
            'lat'         => $lat,
            'lng'         => $lng,
            'photo_1'     => $photo_1,
            'photo_2'     => $photo_2,
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
    );

    if ($inserted === false) {
        rv_decl_delete_photo($photo_1);
        rv_decl_delete_photo($photo_2);
        error_log('rv_decl insert failed: ' . $wpdb->last_error);
        rv_decl_send_error('L’enregistrement n’a pas marché. Réessayez.', 500);
    }

    rv_decl_rate_hit('success');
    rv_decl_send_ok('C’est bien envoyé. Votre signalement est enregistré.');
}

/**
 * Fichier vraiment envoyé, ou null s’il est absent.
 *
 * @return array<string, mixed>|null
 */
function rv_decl_optional_upload(string $key): ?array {
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
        return null;
    }

    $file = $_FILES[$key];
    if (!isset($file['error']) || is_array($file['error'])) {
        return null;
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    return $file;
}

/**
 * Champ texte du POST, sans tableaux.
 */
function rv_decl_post_text(string $key): string {
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return '';
    }

    return trim(sanitize_text_field(wp_unslash((string) $_POST[$key])));
}

/**
 * Remarque libre. Vide est accepté.
 */
function rv_decl_post_remark(): string {
    if (!isset($_POST['remark']) || is_array($_POST['remark'])) {
        return '';
    }

    return trim(sanitize_textarea_field(wp_unslash((string) $_POST['remark'])));
}

/**
 * Coordonnée en chaîne à 7 décimales, ou null.
 */
function rv_decl_parse_coord(string $value, float $min, float $max): ?string {
    $value = str_replace(',', '.', trim($value));
    if (!preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $value)) {
        return null;
    }

    $number = (float) $value;
    if ($number < $min || $number > $max) {
        return null;
    }

    return sprintf('%.7F', $number);
}

/**
 * Compteur horaire par adresse IP hachée. L’IP n’est pas stockée en base.
 *
 * @param bool $increment false = lire seulement.
 */
function rv_decl_rate_allow(string $bucket, int $limit, bool $increment = true): bool {
    $count = rv_decl_rate_count($bucket);
    if ($count >= $limit) {
        return false;
    }

    if ($increment) {
        rv_decl_rate_hit($bucket);
    }

    return true;
}

/**
 * Valeur actuelle du compteur.
 */
function rv_decl_rate_count(string $bucket): int {
    return (int) get_transient(rv_decl_rate_key($bucket));
}

/**
 * Ajoute 1 au compteur, valable une heure.
 */
function rv_decl_rate_hit(string $bucket): void {
    $key = rv_decl_rate_key($bucket);
    $count = (int) get_transient($key);
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
}

/**
 * Clé de transient. Le sel change d’un site à l’autre.
 */
function rv_decl_rate_key(string $bucket): string {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';

    return 'rv_decl_' . $bucket . '_' . md5(wp_salt('auth') . '|' . $ip);
}

/**
 * Réponse JSON d’erreur. La fonction termine la requête.
 */
function rv_decl_send_error(string $message, int $status = 400): void {
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    wp_send_json(
        [
            'ok'      => false,
            'message' => $message,
        ],
        $status
    );
}

/**
 * Réponse JSON de succès. La fonction termine la requête.
 */
function rv_decl_send_ok(string $message): void {
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex');
    wp_send_json(
        [
            'ok'      => true,
            'message' => $message,
        ],
        200
    );
}
