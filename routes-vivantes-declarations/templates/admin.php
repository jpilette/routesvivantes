<?php
/**
 * Carte des signalements. Réservée aux comptes `manage_options`.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

$rv_logo = RV_DECL_URL . 'assets/img/logo.png';
$rv_json = home_url('/routesvivantes/admin/donnees/');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F2219">
    <meta name="robots" content="noindex, nofollow">
    <title>Les animaux signalés — Routes Vivantes</title>
    <link rel="icon" href="<?php echo esc_url($rv_logo); ?>">
    <?php wp_head(); ?>
</head>
<body class="page-map" data-json="<?php echo esc_url($rv_json); ?>" data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('rv_decl_delete')); ?>">
    <a class="skip" href="#contenu">Aller à la carte</a>
    <header class="top">
        <img class="logo" src="<?php echo esc_url($rv_logo); ?>" width="72" height="72" alt="Routes Vivantes, ouvrons les yeux">
        <div>
            <p class="kicker">Routes Vivantes</p>
            <h1>Les animaux signalés</h1>
        </div>
    </header>

    <main id="contenu">
        <form class="period" id="rv-period">
            <div class="period-copy">
                <h2>Quelle période ?</h2>
                <p>Laissez vide pour tout voir.</p>
            </div>
            <div class="period-grid">
                <label class="date-field">
                    <span>Du</span>
                    <input type="date" name="from" autocomplete="off">
                </label>
                <label class="date-field">
                    <span>Au</span>
                    <input type="date" name="to" autocomplete="off">
                </label>
                <button type="submit" class="btn" id="rv-filter">Afficher</button>
            </div>
        </form>

        <p class="legend">
            <span class="swatch" aria-hidden="true"></span>
            Chaque point est un signalement. Mort ou blessé est indiqué dans la fiche.
        </p>
        <p class="count" id="rv-count" role="status">Chargement des signalements.</p>
        <p class="legend">Pour déplacer un lieu, ouvrez le menu Routes Vivantes dans l’administration WordPress.</p>
        <div id="rv-map" role="region" aria-label="Carte de France des signalements"></div>
    </main>

    <div class="scrim" id="rv-scrim" hidden></div>
    <section class="detail" id="rv-detail" hidden role="dialog" aria-modal="true" aria-labelledby="rv-detail-animal">
        <button type="button" class="btn btn-ghost detail-close" id="rv-detail-close">Fermer</button>
        <p class="detail-icon" id="rv-detail-icon" aria-hidden="true"></p>
        <h2 id="rv-detail-animal" tabindex="-1"></h2>
        <p class="chip" id="rv-detail-status"></p>
        <p><span class="meta">Date</span> <strong id="rv-detail-date"></strong></p>
        <p><span class="meta">Mot</span> <span id="rv-detail-remark"></span></p>
        <p><span class="meta">Latitude</span> <strong id="rv-detail-lat"></strong></p>
        <p><span class="meta">Longitude</span> <strong id="rv-detail-lng"></strong></p>
        <figure>
            <img id="rv-photo-1" alt="" hidden>
            <figcaption id="rv-caption-1">Photo 1</figcaption>
        </figure>
        <figure>
            <img id="rv-photo-2" alt="" hidden>
            <figcaption id="rv-caption-2">Photo 2</figcaption>
        </figure>
        <div class="detail-delete">
            <button type="button" class="btn btn-danger" id="rv-delete-ask">Supprimer ce signalement</button>
            <div id="rv-delete-confirm" hidden>
                <p>Voulez-vous supprimer ce signalement ?</p>
                <p>Les photos seront aussi supprimées.</p>
                <button type="button" class="btn btn-danger" id="rv-delete-yes">Oui, supprimez-le</button>
                <button type="button" class="btn btn-ghost" id="rv-delete-no">Non, gardez-le</button>
            </div>
            <p class="delete-error" id="rv-delete-error" role="alert" hidden></p>
        </div>
    </section>

    <noscript>
        <p class="noscript">Cette carte a besoin de JavaScript.</p>
    </noscript>
    <?php wp_footer(); ?>
</body>
</html>
