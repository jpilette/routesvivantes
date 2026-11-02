=== Routes Vivantes — Déclarations ===
Contributors: routesvivantes
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Formulaire public pour déclarer un animal mort ou blessé, et carte des signalements pour les administrateurs.

== Description ==

Le plugin ajoute deux pages indépendantes du thème WordPress.

* `/routesvivantes/` — formulaire en français facile à lire. Il commence par l’état de l’animal. Le lieu n’est demandé que si le téléphone ne l’a pas déjà autorisé. Ensuite : type d’animal, deux photos prises avec l’appareil photo, mot libre.
* `/routesvivantes/admin/` — carte de France (Leaflet, fond OpenStreetMap). Chaque signalement est un point. Un clic ouvre la fiche. Le seul filtre est une période.

La carte, le JSON et les photos sont réservés aux comptes qui ont la capacité `manage_options`.

Le code est sous GPL. Le logo de l’association Routes Vivantes reste sa marque. La police Outfit est sous SIL Open Font License (voir `assets/fonts/OFL.txt`). Leaflet 1.9.4 est sous licence BSD à 2 clauses. Plugin Update Checker 5.7 (Yahnis Elsts) est sous licence MIT, dans `plugin-update-checker/`.

== Installation ==

1. Copier le dossier `routes-vivantes-declarations` dans `wp-content/plugins/`, ou envoyer le zip de ce dossier dans Extensions → Ajouter.
2. Dans l’administration WordPress, ouvrir Extensions.
3. Activer « Routes Vivantes — Déclarations ».
   L’activation crée la table `wp_rv_declarations` (le préfixe peut différer) et enregistre les adresses.
4. Ouvrir Réglages → Permaliens. Choisir une structure autre que « Simple », puis enregistrer.
5. Ouvrir `https://exemple.fr/routesvivantes/` pour le formulaire.
6. Ouvrir `https://exemple.fr/routesvivantes/admin/` pour la carte. Il faut un compte administrateur.

La désactivation ne supprime ni la table ni les photos. Elle retire seulement les adresses du plugin.

== Formulaire public ==

Le visiteur n’a pas besoin de compte. La première question est : l’animal est mort ou blessé. Le lieu est lu tout de suite si le téléphone l’autorise déjà. Sinon, une question simple demande de l’autoriser. L’envoi reste bloqué tant que la position manque.

L’envoi vérifie un nonce, la taille des photos (8 Mo chacune) et un plafond de 8 signalements par heure et par adresse IP. L’adresse IP n’est pas enregistrée : seul un compteur temporaire est utilisé.

Champs enregistrés : date, état (`mort` ou `blessé`), animal, remarque, latitude, longitude, chemins des deux photos.

Animaux proposés : hérisson, renard, blaireau, chevreuil, sanglier, oiseau, chouette, crapaud, lapin, lièvre, écureuil, fouine, ragondin, chat, autre.

Les photos sont réenregistrées en JPEG (GD ou Imagick, via l’éditeur d’images WordPress), sous `wp-content/uploads/routes-vivantes-declarations/`. Un fichier `.htaccess` interdit la lecture directe sur Apache. Les noms sont aléatoires et ne sont pas affichés dans la page publique.

Sur Nginx, ajouter une règle qui refuse ce dossier, par exemple :

`location ^~ /wp-content/uploads/routes-vivantes-declarations/ { deny all; }`

Ne pas mettre `/routesvivantes/` en cache : la page contient un nonce.

== Carte ==

* Adresse : `/routesvivantes/admin/`
* Données : `/routesvivantes/admin/donnees/` (JSON, même protection)
* Photo : `/routesvivantes/admin/photo/{id}/1/` et `/2/`

Un visiteur anonyme est renvoyé vers la connexion WordPress. Un compte connecté sans `manage_options` aussi, sans revenir sur la carte (pour éviter une boucle).

La période est optionnelle. Vide = tous les signalements. Une seule date borne un seul côté. Les dates suivent le fuseau horaire du site.

La carte s’ouvre cadrée sur la France métropolitaine.

== Mise à jour ==

Le plugin cherche lui-même une version plus récente sur le dépôt public `https://github.com/jpilette/routesvivantes`. Aucun jeton n’est demandé.

La vérification suit cet ordre :

1. la dernière release GitHub (les préversions sont ignorées) ;
2. le tag dont le numéro est le plus élevé ;
3. la branche `main`, si aucune release ni aucun tag ne convient.

Dès qu’une release ou un tag existe, la branche n’est plus consultée. Pour qu’une version sur `main` soit proposée, il faut une nouvelle release ou un nouveau tag, ou bien ne pas en avoir du tout.

Pour publier une version :

1. Monter l’en-tête `Version` dans `routes-vivantes-declarations.php` et `Stable tag` dans ce fichier. Les deux portent le même numéro, par exemple `1.2.0`.
2. Fusionner ce changement dans la branche `main`.

Puis l’une de ces trois voies :

* **Release.** Créer une release GitHub, pas une préversion. Le tag s’appelle `v1.2.0` ou `1.2.0`. Joindre `routes-vivantes-declarations.zip` : ce zip contient seulement le dossier du plugin, afin que WordPress puisse l’installer. S’il est présent, c’est ce fichier qui est téléchargé.
* **Tag.** Poser le tag `v1.2.0` ou `1.2.0` sur `main`, sans créer de release. Le numéro du tag est la version annoncée, puis l’en-tête `Version` du tag la remplace s’il est lisible.
* **Branche.** Pousser l’en-tête `Version` sur `main`, sans tag ni release. Le vérificateur lit cet en-tête.

L’archive automatique de GitHub enveloppe tout le dépôt. À l’installation, seul le dossier `routes-vivantes-declarations` est conservé. Le zip joint à la release évite ce détour.

La bibliothèque n’est chargée que dans l’administration WordPress, dans WP-CLI et dans le cron. Les pages `/routesvivantes/` et `/routesvivantes/admin/` ne la chargent pas.

== Changelog ==

= 1.2.0 =
* Le formulaire commence par le signalement. Le lieu n’est demandé que s’il n’est pas déjà autorisé.
* Les textes du formulaire, de la carte et des messages s’adressent à vous.

= 1.1.0 =
* Mise à jour automatique depuis le dépôt GitHub public.

= 1.0.0 =
* Formulaire public et carte des signalements.

== Notes ==

Ce dépôt ne fournit pas WordPress. Le plugin se place dans une installation existante.
