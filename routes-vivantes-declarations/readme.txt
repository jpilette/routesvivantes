=== Routes Vivantes — Déclarations ===
Contributors: routesvivantes
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Formulaire public pour déclarer un animal mort ou blessé, et carte des signalements pour les administrateurs.

== Description ==

Le plugin ajoute deux pages indépendantes du thème WordPress.

* `/routesvivantes/` — formulaire en français facile à lire. Lieu du téléphone, deux photos prises avec l’appareil photo, type d’animal, mot libre, mort ou blessé.
* `/routesvivantes/admin/` — carte de France (Leaflet, fond OpenStreetMap). Chaque signalement est un point. Un clic ouvre la fiche. Le seul filtre est une période.

La carte, le JSON et les photos sont réservés aux comptes qui ont la capacité `manage_options`.

Le code est sous GPL. Le logo de l’association Routes Vivantes reste sa marque. La police Outfit est sous SIL Open Font License (voir `assets/fonts/OFL.txt`). Leaflet 1.9.4 est sous licence BSD à 2 clauses.

== Installation ==

1. Copier le dossier `routes-vivantes-declarations` dans `wp-content/plugins/`.
2. Dans l’administration WordPress, ouvrir Extensions.
3. Activer « Routes Vivantes — Déclarations ».
   L’activation crée la table `wp_rv_declarations` (le préfixe peut différer) et enregistre les adresses.
4. Ouvrir Réglages → Permaliens. Choisir une structure autre que « Simple », puis enregistrer.
5. Ouvrir `https://exemple.fr/routesvivantes/` pour le formulaire.
6. Ouvrir `https://exemple.fr/routesvivantes/admin/` pour la carte. Il faut un compte administrateur.

La désactivation ne supprime ni la table ni les photos. Elle retire seulement les adresses du plugin.

== Formulaire public ==

Le visiteur n’a pas besoin de compte. L’envoi vérifie un nonce, la taille des photos (8 Mo chacune) et un plafond de 8 signalements par heure et par adresse IP. L’adresse IP n’est pas enregistrée : seul un compteur temporaire est utilisé.

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

== Notes ==

Ce dépôt ne fournit pas WordPress. Le plugin se place dans une installation existante.
