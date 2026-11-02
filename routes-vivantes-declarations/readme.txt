=== Routes Vivantes — Déclarations ===
Contributors: routesvivantes
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Formulaire public pour déclarer un animal mort ou blessé, et carte des signalements pour les administrateurs.

== Description ==

Le plugin ajoute deux pages indépendantes du thème WordPress.

* `/routesvivantes/` — formulaire en français facile à lire. Il commence par l’état de l’animal. Le lieu n’est demandé que si le téléphone ne l’a pas déjà autorisé. Ensuite : type d’animal, une photo obligatoire et une deuxième photo facultative, mot libre.
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

Champs enregistrés : date, état (`mort` ou `blessé`), animal, remarque, latitude, longitude, chemin de la photo 1. La photo 2 est enregistrée seulement si elle a été prise.

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

Un administrateur peut supprimer un signalement depuis sa fiche. La carte demande une confirmation. La ligne et les fichiers photo sont alors retirés. Un visiteur anonyme ne peut pas supprimer.

La période est optionnelle. Vide = tous les signalements. Une seule date borne un seul côté. Les dates suivent le fuseau horaire du site.

La carte s’ouvre cadrée sur la France métropolitaine.

== Mise à jour ==

Le plugin cherche une version plus récente dans un fichier JSON public. Aucun jeton n’est demandé. Il n’appelle pas l’API GitHub (`api.github.com`) : un refus 403 de cette API ne bloque plus la recherche.

Adresse du fichier :

`https://raw.githubusercontent.com/jpilette/routesvivantes/cursor/routes-vivantes-declarations-e73d/routes-vivantes-declarations/update.json`

Ce fichier donne le numéro de version et l’adresse du zip. Le zip est celui de la release GitHub, par exemple `https://github.com/jpilette/routesvivantes/releases/download/v1.4.0/routes-vivantes-declarations.zip`. Il contient seulement le dossier du plugin.

Pour publier une version :

1. Monter l’en-tête `Version`, le `Stable tag` de ce fichier, et le champ `version` de `update.json`. Les trois portent le même numéro.
2. Mettre dans `download_url` l’adresse exacte du zip de cette version.
3. Pousser la branche qui contient `update.json`. Ne pas réécrire `main`.
4. Créer une release GitHub, pas une préversion. Le tag s’appelle `vX.Y.Z`. Joindre `routes-vivantes-declarations.zip`.

Une installation restée en 1.1.0 appelle encore l’API GitHub. Vous installez ce zip une fois par Extensions → Ajouter. Ensuite, les versions suivantes s’installent depuis la liste des extensions.

La bibliothèque est chargée dans l’administration WordPress, dans WP-CLI, dans le cron, et dans la requête qui installe la mise à jour. Les pages `/routesvivantes/` et `/routesvivantes/admin/`, ainsi que l’envoi du formulaire, ne la chargent pas.

== Changelog ==

= 1.4.0 =
* La recherche de mise à jour lit un fichier public. Elle n’appelle plus l’API GitHub.
* La mise à jour lancée depuis la liste des extensions installe la dernière version publiée.

= 1.3.0 =
* Une seule photo est obligatoire. La deuxième est facultative.
* Un administrateur peut supprimer un signalement et ses photos, après confirmation.

= 1.2.0 =
* Le formulaire commence par le signalement. Le lieu n’est demandé que s’il n’est pas déjà autorisé.
* Les textes du formulaire, de la carte et des messages s’adressent à vous.

= 1.1.0 =
* Mise à jour automatique depuis le dépôt GitHub public.

= 1.0.0 =
* Formulaire public et carte des signalements.

== Notes ==

Ce dépôt ne fournit pas WordPress. Le plugin se place dans une installation existante.
