<?php
/**
 * Données de la carte. Jamais accessibles sans `manage_options`.
 *
 * Le JSON ne contient pas les chemins des fichiers.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /routesvivantes/admin/donnees/?from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Une date vide veut dire « sans borne ». Les deux vides : tout.
 */
function rv_decl_serve_json(): void {
    rv_decl_require_manager();

    $from = rv_decl_read_date_param('from');
    if (is_wp_error($from)) {
        rv_decl_admin_json_error($from->get_error_message(), 400);
    }

    $to = rv_decl_read_date_param('to');
    if (is_wp_error($to)) {
        rv_decl_admin_json_error($to->get_error_message(), 400);
    }

    if ($from !== null && $to !== null && $from > $to) {
        rv_decl_admin_json_error('La première date est après la seconde.', 400);
    }

    $rows = rv_decl_query_declarations($from, $to);
    $declarations = [];
    foreach ($rows as $row) {
        $declarations[] = rv_decl_present_declaration($row);
    }

    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: private, no-store');
    wp_send_json(['declarations' => $declarations], 200);
}

/**
 * GET /routesvivantes/admin/photo/{id}/{1|2}/
 */
function rv_decl_serve_photo(): void {
    rv_decl_require_manager();

    $id = (int) get_query_var('rv_decl_id');
    $slot = (int) get_query_var('rv_decl_slot');
    if ($id < 1 || ($slot !== 1 && $slot !== 2)) {
        rv_decl_photo_not_found();
    }

    global $wpdb;
    $column = $slot === 1 ? 'photo_1' : 'photo_2';
    $relative = $wpdb->get_var(
        $wpdb->prepare(
            'SELECT ' . $column . ' FROM ' . rv_decl_table_name() . ' WHERE id = %d',
            $id
        )
    );

    if (!is_string($relative) || $relative === '') {
        rv_decl_photo_not_found();
    }

    $absolute = rv_decl_absolute_from_relative($relative);
    if ($absolute === null) {
        rv_decl_photo_not_found();
    }

    $info = @getimagesize($absolute);
    if (!is_array($info) || ($info['mime'] ?? '') !== 'image/jpeg') {
        rv_decl_photo_not_found();
    }

    nocache_headers();
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string) filesize($absolute));
    header('Content-Disposition: inline; filename="signalement-' . $id . '-' . $slot . '.jpg"');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: private, no-store');
    readfile($absolute);
    exit;
}

/**
 * @return string|\WP_Error|null null si le paramètre est absent ou vide.
 */
function rv_decl_read_date_param(string $key) {
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return null;
    }

    $raw = trim((string) wp_unslash($_GET[$key]));
    if ($raw === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('!Y-m-d', $raw);
    $errors = DateTime::getLastErrors();
    $has_error = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);
    if (!$dt || $has_error || $dt->format('Y-m-d') !== $raw) {
        return new WP_Error('rv_date', 'La date n’est pas bonne. Utilisez le calendrier.');
    }

    return $raw;
}

/**
 * @return array<int, array<string, mixed>>
 */
function rv_decl_query_declarations(?string $from, ?string $to): array {
    global $wpdb;

    $where = [];
    $params = [];
    if ($from !== null) {
        $where[] = 'created_at >= %s';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== null) {
        $where[] = 'created_at <= %s';
        $params[] = $to . ' 23:59:59';
    }

    $sql = 'SELECT id, created_at, status, animal_type, remark, lat, lng, photo_1, photo_2 FROM ' . rv_decl_table_name();
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC';

    if ($params) {
        $prepared = $wpdb->prepare($sql, ...$params);
    } else {
        $prepared = $sql;
    }

    $rows = $wpdb->get_results($prepared, ARRAY_A);

    return is_array($rows) ? $rows : [];
}

/**
 * Forme envoyée au navigateur. Pas de chemin de fichier.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function rv_decl_present_declaration(array $row): array {
    $animals = rv_decl_animals();
    $statuses = rv_decl_statuses();
    $animal_key = (string) ($row['animal_type'] ?? '');
    $status_key = (string) ($row['status'] ?? '');
    $id = (int) ($row['id'] ?? 0);

    return [
        'id'           => $id,
        'date_label'   => rv_decl_date_label((string) ($row['created_at'] ?? '')),
        'status'       => $status_key,
        'status_label' => $statuses[$status_key] ?? $status_key,
        'animal_type'  => $animal_key,
        'animal_label' => $animals[$animal_key]['label'] ?? $animal_key,
        'icon'         => $animals[$animal_key]['icon'] ?? '',
        'remark'       => (string) ($row['remark'] ?? ''),
        'lat'          => (float) ($row['lat'] ?? 0),
        'lng'          => (float) ($row['lng'] ?? 0),
        'photo_1'      => rv_decl_photo_url($id, 1, (string) ($row['photo_1'] ?? '')),
        'photo_2'      => rv_decl_photo_url($id, 2, (string) ($row['photo_2'] ?? '')),
    ];
}

/**
 * URL authentifiée, ou chaîne vide si le fichier n’est plus là.
 */
function rv_decl_photo_url(int $id, int $slot, string $relative): string {
    if ($id < 1 || rv_decl_absolute_from_relative($relative) === null) {
        return '';
    }

    return home_url('/routesvivantes/admin/photo/' . $id . '/' . $slot . '/');
}

/**
 * Date lisible, dans le fuseau horaire du site.
 */
function rv_decl_date_label(string $mysql): string {
    $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysql, $timezone);
    if (!$dt) {
        return $mysql;
    }

    $months = [
        1  => 'janvier',
        2  => 'février',
        3  => 'mars',
        4  => 'avril',
        5  => 'mai',
        6  => 'juin',
        7  => 'juillet',
        8  => 'août',
        9  => 'septembre',
        10 => 'octobre',
        11 => 'novembre',
        12 => 'décembre',
    ];
    $month = $months[(int) $dt->format('n')] ?? '';

    return (int) $dt->format('j') . ' ' . $month . ' ' . $dt->format('Y') . ', ' . $dt->format('H') . ' h ' . $dt->format('i');
}

/**
 * Photo absente. Le message ne donne pas de chemin.
 */
function rv_decl_photo_not_found(): void {
    status_header(404);
    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    echo 'Photo introuvable.';
    exit;
}

/**
 * Erreur JSON de la carte. Termine la requête.
 */
function rv_decl_admin_json_error(string $message, int $status): void {
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cross-Origin-Resource-Policy: same-origin');
    wp_send_json(['error' => $message], $status);
}
