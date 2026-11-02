<?php
/**
 * Photos sur disque, hors accès HTTP direct.
 *
 * Les fichiers vivent sous uploads/routes-vivantes-declarations/.
 * Un .htaccess y interdit la lecture directe (Apache). Les noms sont
 * aléatoires et ne sont jamais renvoyés au navigateur : la carte passe
 * par /routesvivantes/admin/photo/{id}/{1|2}/ après contrôle du compte.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dossier racine des photos, ou null si uploads est inutilisable.
 */
function rv_decl_storage_root(): ?string {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return null;
    }

    return wp_normalize_path(trailingslashit($uploads['basedir']) . 'routes-vivantes-declarations');
}

/**
 * Crée le dossier et bloque le listing / l’exécution.
 */
function rv_decl_protect_storage(): void {
    $root = rv_decl_storage_root();
    if ($root === null) {
        return;
    }

    if (!wp_mkdir_p($root)) {
        return;
    }

    $index = $root . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php\n// Silence is golden.\n");
    }

    $htaccess = $root . '/.htaccess';
    if (!file_exists($htaccess)) {
        $rules = <<<'HT'
# Routes Vivantes — pas d’accès HTTP direct aux photos.
# Elles passent par /routesvivantes/admin/photo/ pour les administrateurs.
<IfModule mod_authz_core.c>
	Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
	Deny from all
</IfModule>
Options -Indexes
HT;
        file_put_contents($htaccess, $rules);
    }

    $web_config = $root . '/web.config';
    if (!file_exists($web_config)) {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <authorization>
      <deny users="*" />
    </authorization>
  </system.webServer>
</configuration>
XML;
        file_put_contents($web_config, $xml);
    }
}

/**
 * Chemin disque d’une photo enregistrée, après contrôle du motif.
 *
 * Retourne null si le chemin sort du dossier prévu ou si le fichier manque.
 */
function rv_decl_absolute_from_relative(string $relative): ?string {
    $relative = str_replace('\\', '/', $relative);
    $relative = ltrim($relative, '/');

    if (!preg_match('#^routes-vivantes-declarations/[0-9]{4}/[0-9]{2}/[a-f0-9]{32}\\.jpg$#', $relative)) {
        return null;
    }

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return null;
    }

    $basedir = wp_normalize_path(trailingslashit($uploads['basedir']));
    $path = $basedir . $relative;
    $root = wp_normalize_path(trailingslashit($uploads['basedir']) . 'routes-vivantes-declarations');

    if (strpos(wp_normalize_path($path), $root . '/') !== 0) {
        return null;
    }

    $real_root = realpath($root);
    $real_path = realpath($path);
    if ($real_root === false || $real_path === false || !is_file($real_path)) {
        return null;
    }

    $prefix = rtrim($real_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($real_path, $prefix) !== 0) {
        return null;
    }

    return $real_path;
}

/**
 * Enregistre une photo téléversée en JPEG redimensionné.
 *
 * @param array<string, mixed> $file Une entrée de $_FILES.
 * @return string|\WP_Error Chemin relatif à basedir, ou une erreur.
 */
function rv_decl_store_uploaded_photo(array $file) {
    foreach (['tmp_name', 'error', 'size'] as $key) {
        if (!isset($file[$key]) || is_array($file[$key])) {
            return new WP_Error('rv_photo', 'Les 2 photos sont obligatoires.');
        }
    }

    $error = (int) $file['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return new WP_Error('rv_photo', 'Les 2 photos sont obligatoires.');
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return new WP_Error('rv_photo', 'Une photo est trop lourde. 8 Mo maximum.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        return new WP_Error('rv_photo', 'Une photo n’est pas arrivée. Reprenez-la.');
    }

    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return new WP_Error('rv_photo', 'Une photo n’est pas arrivée. Reprenez-la.');
    }

    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size < 1 || $size > RV_DECL_MAX_BYTES) {
        return new WP_Error('rv_photo', 'Une photo est trop lourde. 8 Mo maximum.');
    }

    $info = @getimagesize($tmp);
    if (!is_array($info) || empty($info['mime']) || empty($info[0]) || empty($info[1])) {
        return new WP_Error('rv_photo', 'Cette photo n’est pas lisible. Reprenez-la.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    $mime = (string) $info['mime'];
    if (!isset($allowed[$mime])) {
        return new WP_Error('rv_photo', 'Cette photo n’est pas au bon format. Reprenez-la.');
    }

    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < 32 || $height < 32 || ($width * $height) > RV_DECL_MAX_PIXELS) {
        return new WP_Error('rv_photo', 'Cette photo n’est pas lisible. Reprenez-la.');
    }

    $checked = wp_check_filetype_and_ext($tmp, 'photo.' . $allowed[$mime]);
    if (empty($checked['type']) || empty($checked['ext'])) {
        return new WP_Error('rv_photo', 'Cette photo n’est pas lisible. Reprenez-la.');
    }

    $root = rv_decl_storage_root();
    if ($root === null) {
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    rv_decl_protect_storage();

    $relative_dir = 'routes-vivantes-declarations/' . gmdate('Y/m');
    $uploads = wp_upload_dir();
    $absolute_dir = wp_normalize_path(trailingslashit($uploads['basedir']) . $relative_dir);
    if (!wp_mkdir_p($absolute_dir)) {
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    $month_index = $absolute_dir . '/index.php';
    if (!file_exists($month_index)) {
        file_put_contents($month_index, "<?php\n// Silence is golden.\n");
    }

    if (!function_exists('wp_get_image_editor')) {
        require_once ABSPATH . 'wp-includes/media.php';
    }
    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('image');
    }

    $editor = wp_get_image_editor($tmp);
    if (is_wp_error($editor)) {
        return new WP_Error('rv_photo', 'Cette photo n’est pas lisible. Reprenez-la.');
    }

    $orientation = rv_decl_exif_orientation($tmp);
    if ($orientation === 3) {
        $editor->rotate(180);
    } elseif ($orientation === 6) {
        $editor->rotate(-90);
    } elseif ($orientation === 8) {
        $editor->rotate(90);
    }

    $editor->set_quality(85);
    $current = $editor->get_size();
    if (is_array($current) && ($current['width'] > RV_DECL_MAX_EDGE || $current['height'] > RV_DECL_MAX_EDGE)) {
        $resized = $editor->resize(RV_DECL_MAX_EDGE, RV_DECL_MAX_EDGE, false);
        if (is_wp_error($resized)) {
            return new WP_Error('rv_photo', 'Cette photo n’est pas lisible. Reprenez-la.');
        }
    }

    try {
        $filename = bin2hex(random_bytes(16)) . '.jpg';
    } catch (Exception $exception) {
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    $destination = $absolute_dir . '/' . $filename;
    $saved = $editor->save($destination, 'image/jpeg');
    if (is_wp_error($saved) || empty($saved['path'])) {
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    $saved_path = wp_normalize_path((string) $saved['path']);
    $basedir = wp_normalize_path(trailingslashit($uploads['basedir']));
    if (strpos($saved_path, $basedir) !== 0) {
        wp_delete_file($saved_path);
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    $relative = ltrim(substr($saved_path, strlen($basedir)), '/');
    $absolute = rv_decl_absolute_from_relative($relative);
    if ($absolute === null) {
        wp_delete_file($saved_path);
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    $written = @getimagesize($absolute);
    if (!is_array($written) || ($written['mime'] ?? '') !== 'image/jpeg') {
        wp_delete_file($absolute);
        return new WP_Error('rv_photo', 'L’enregistrement des photos a échoué. Réessayez.');
    }

    @chmod($absolute, 0644);

    return $relative;
}

/**
 * Orientation EXIF, ou 1 si l’extension est absente.
 */
function rv_decl_exif_orientation(string $file): int {
    if (!function_exists('exif_read_data')) {
        return 1;
    }

    $exif = @exif_read_data($file);
    if (!is_array($exif) || empty($exif['Orientation'])) {
        return 1;
    }

    return (int) $exif['Orientation'];
}

/**
 * Supprime une photo déjà validée. Ignore un chemin douteux.
 */
function rv_decl_delete_photo(string $relative): void {
    $absolute = rv_decl_absolute_from_relative($relative);
    if ($absolute !== null) {
        wp_delete_file($absolute);
    }
}
