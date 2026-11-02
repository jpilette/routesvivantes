<?php
/**
 * Formulaire public. Aucun élément du thème n’est imprimé autour.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

$rv_animals = rv_decl_animals();
$rv_nonce = wp_create_nonce('rv_decl_submit');
$rv_ajax = admin_url('admin-ajax.php');
$rv_logo = RV_DECL_URL . 'assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F2219">
    <meta name="description" content="Signaler un animal mort ou blessé sur la route. Le lieu et deux photos sont demandés.">
    <title>Signaler un animal — Routes Vivantes</title>
    <link rel="icon" href="<?php echo esc_url($rv_logo); ?>">
    <?php wp_head(); ?>
</head>
<body class="page-form" data-ajax="<?php echo esc_url($rv_ajax); ?>">
    <a class="skip" href="#contenu">Aller au contenu</a>
    <div class="app">
        <header class="top">
            <img class="logo" src="<?php echo esc_url($rv_logo); ?>" width="72" height="72" alt="Routes Vivantes, ouvrons les yeux">
            <div>
                <p class="kicker">Routes Vivantes</p>
                <h1>Signaler un animal</h1>
            </div>
        </header>

        <div class="progress-wrap" id="rv-progress-wrap">
            <div class="bar" aria-hidden="true"><span id="rv-bar"></span></div>
            <p class="progress" id="rv-progress">Étape 1 sur 5</p>
        </div>

        <main id="contenu">
            <div class="error" id="rv-error" role="alert" hidden></div>
            <form id="rv-form" autocomplete="off" novalidate>
                <input type="hidden" name="nonce" value="<?php echo esc_attr($rv_nonce); ?>">
                <input type="hidden" name="lat" id="rv-lat" value="">
                <input type="hidden" name="lng" id="rv-lng" value="">

                <section class="step" data-part="etat" data-step="1" aria-labelledby="rv-title-1">
                    <h2 id="rv-title-1" tabindex="-1">L’animal est comment ?</h2>
                    <p>Choisissez une seule réponse.</p>
                    <div class="choices-stack">
                        <label class="choice">
                            <input type="radio" name="status" value="mort">
                            <span class="choice-title">Mort</span>
                            <span class="hint">Il ne bouge plus.</span>
                        </label>
                        <label class="choice">
                            <input type="radio" name="status" value="blessé">
                            <span class="choice-title">Blessé</span>
                            <span class="hint">Il est vivant.</span>
                        </label>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn" data-next>Continuer</button>
                    </div>
                </section>

                <section class="step" data-part="lieu" data-step="2" hidden aria-labelledby="rv-title-2">
                    <h2 id="rv-title-2" tabindex="-1">Autorisez le lieu</h2>
                    <p>Nous avons besoin du lieu.</p>
                    <p>Touchez le bouton. Puis choisissez Autoriser.</p>
                    <div class="geo" id="rv-geo" data-geo="waiting" role="status" aria-live="polite">
                        <p class="geo-label">Lieu : <strong id="rv-geo-label">En attente</strong></p>
                        <p id="rv-geo-text">Le lieu n’est pas encore noté.</p>
                        <div class="help" id="rv-geo-help" hidden>
                            <p>Sans le lieu, l’envoi est bloqué.</p>
                            <p>Pour autoriser le lieu :</p>
                            <ol>
                                <li>Ouvrez les réglages du téléphone.</li>
                                <li>Ouvrez le navigateur. Safari ou Chrome.</li>
                                <li>Touchez Position.</li>
                                <li>Choisissez Autoriser.</li>
                                <li>Revenez sur cette page.</li>
                                <li>Touchez « Réessayer le lieu ».</li>
                            </ol>
                        </div>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn" id="rv-geo-action">Autoriser le lieu</button>
                        <button type="button" class="btn btn-ghost" data-back>Retour</button>
                    </div>
                </section>

                <section class="step" data-part="animal" data-step="3" hidden aria-labelledby="rv-title-3">
                    <h2 id="rv-title-3" tabindex="-1">Quel animal ?</h2>
                    <p>Choisissez un seul animal.</p>
                    <div class="animals">
                        <?php foreach ($rv_animals as $rv_key => $rv_animal) : ?>
                            <label class="choice">
                                <input type="radio" name="animal_type" value="<?php echo esc_attr($rv_key); ?>">
                                <span class="icon" aria-hidden="true"><?php echo esc_html($rv_animal['icon']); ?></span>
                                <span class="choice-title"><?php echo esc_html($rv_animal['label']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn" data-next>Continuer</button>
                        <button type="button" class="btn btn-ghost" data-back>Retour</button>
                    </div>
                </section>

                <?php
                $rv_photo_steps = [
                    1 => [
                        'step'  => 4,
                        'title' => 'Photo 1',
                        'lead'  => 'Prenez une photo de l’animal.',
                        'more'  => 'Utilisez l’appareil photo. Pas les anciennes photos.',
                    ],
                    2 => [
                        'step'  => 5,
                        'title' => 'Photo 2',
                        'lead'  => 'Prenez une autre photo.',
                        'more'  => 'Montrez l’animal d’un autre côté.',
                    ],
                ];
                foreach ($rv_photo_steps as $rv_index => $rv_photo) :
                    ?>
                    <section class="step" data-part="photo" data-step="<?php echo (int) $rv_photo['step']; ?>" data-photo="<?php echo (int) $rv_index; ?>" hidden aria-labelledby="rv-title-<?php echo (int) $rv_photo['step']; ?>">
                        <h2 id="rv-title-<?php echo (int) $rv_photo['step']; ?>" tabindex="-1"><?php echo esc_html($rv_photo['title']); ?></h2>
                        <p><?php echo esc_html($rv_photo['lead']); ?></p>
                        <p><?php echo esc_html($rv_photo['more']); ?></p>
                        <div class="help" data-camera-help hidden>
                            <p>L’appareil photo est refusé.</p>
                            <p>Pour autoriser la photo :</p>
                            <ol>
                                <li>Ouvrez les réglages du téléphone.</li>
                                <li>Ouvrez le navigateur.</li>
                                <li>Touchez Appareil photo.</li>
                                <li>Choisissez Autoriser.</li>
                                <li>Revenez ici.</li>
                                <li>Touchez « Ouvrir l’appareil photo ».</li>
                            </ol>
                        </div>
                        <div class="preview" data-preview="<?php echo (int) $rv_index; ?>" hidden>
                            <img alt="Aperçu de la photo <?php echo (int) $rv_index; ?>">
                            <button type="button" class="btn btn-ghost" data-retake="<?php echo (int) $rv_index; ?>">Reprendre la photo</button>
                        </div>
                        <button type="button" class="btn" data-open-camera="<?php echo (int) $rv_index; ?>">Ouvrir l’appareil photo</button>
                        <button type="button" class="btn btn-ghost" data-fallback="<?php echo (int) $rv_index; ?>" hidden>Appareil photo du téléphone</button>
                        <input type="file" accept="image/*" capture="environment" data-file="<?php echo (int) $rv_index; ?>" hidden>
                        <div class="actions">
                            <button type="button" class="btn" data-next>Continuer</button>
                            <button type="button" class="btn btn-ghost" data-back>Retour</button>
                        </div>
                    </section>
                <?php endforeach; ?>

                <section class="step" data-part="mot" data-step="6" hidden aria-labelledby="rv-title-6">
                    <h2 id="rv-title-6" tabindex="-1">Un mot en plus ?</h2>
                    <p id="rv-remark-help">Ce n’est pas obligatoire. 500 signes maximum.</p>
                    <p class="example">Exemple : près du fossé.</p>
                    <label class="field-label" for="rv-remark">Votre mot</label>
                    <textarea id="rv-remark" name="remark" maxlength="500" rows="4" aria-describedby="rv-remark-help"></textarea>
                    <p class="recap" id="rv-recap"></p>
                    <p id="rv-lieu-wait" hidden>Nous notons le lieu. Patientez.</p>
                    <div class="actions">
                        <button type="submit" class="btn" id="rv-submit">Envoyer le signalement</button>
                        <button type="button" class="btn btn-ghost" data-back>Retour</button>
                    </div>
                </section>
            </form>

            <section class="success" id="rv-success" hidden aria-labelledby="rv-success-title">
                <div class="success-mark" aria-hidden="true">
                    <svg viewBox="0 0 72 72" width="72" height="72" focusable="false">
                        <circle cx="36" cy="36" r="36" fill="#D3B887"></circle>
                        <path d="M20 38 l10 10 22-24" fill="none" stroke="#1E4D37" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
                <h2 id="rv-success-title" tabindex="-1">Merci.</h2>
                <p>C’est bien envoyé.</p>
                <p>Votre signalement est enregistré.</p>
                <p>Vous pouvez fermer la page.</p>
                <button type="button" class="btn" id="rv-again">Faire un autre signalement</button>
            </section>
        </main>

        <p class="fine">Nous gardons le lieu, l’animal et les 2 photos.</p>
    </div>

    <div class="camera" id="rv-camera" hidden>
        <p class="camera-hint">Placez l’animal dans le cadre.</p>
        <div class="camera-frame">
            <video id="rv-video" playsinline muted></video>
        </div>
        <div class="camera-actions">
            <button type="button" class="shutter" id="rv-shutter" aria-label="Prendre la photo"></button>
            <button type="button" class="btn btn-light" id="rv-camera-cancel">Annuler</button>
        </div>
    </div>

    <noscript>
        <p class="noscript">Cette page a besoin de JavaScript pour le lieu et l’appareil photo.</p>
    </noscript>
    <?php wp_footer(); ?>
</body>
</html>
