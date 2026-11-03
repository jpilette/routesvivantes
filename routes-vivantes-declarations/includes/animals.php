<?php
/**
 * Liste fermée des animaux et des états.
 *
 * Les clés sont enregistrées telles quelles en base.
 *
 * @package RoutesVivantesDeclarations
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Animaux proposés dans le formulaire.
 *
 * L’icône est décorative : le texte seul doit suffire.
 *
 * @return array<string, array{label: string, icon: string}>
 */
function rv_decl_animals(): array {
    return [
        'hérisson'  => ['label' => 'Hérisson', 'icon' => '🦔'],
        'renard'    => ['label' => 'Renard', 'icon' => '🦊'],
        'blaireau'  => ['label' => 'Blaireau', 'icon' => '🦡'],
        'chevreuil' => ['label' => 'Chevreuil', 'icon' => '🦌'],
        'sanglier'  => ['label' => 'Sanglier', 'icon' => '🐗'],
        'oiseau'    => ['label' => 'Oiseau', 'icon' => '🐦'],
        'chouette'  => ['label' => 'Chouette', 'icon' => '🦉'],
        'crapaud'   => ['label' => 'Crapaud', 'icon' => '🐸'],
        'lapin'     => ['label' => 'Lapin', 'icon' => '🐰'],
        'lièvre'    => ['label' => 'Lièvre', 'icon' => '🐇'],
        'écureuil'  => ['label' => 'Écureuil', 'icon' => '🐿️'],
        'fouine'    => ['label' => 'Fouine', 'icon' => '🐾'],
        'ragondin'  => ['label' => 'Ragondin', 'icon' => '🦫'],
        'chat'      => ['label' => 'Chat', 'icon' => '🐱'],
        'autre'     => ['label' => 'Autre', 'icon' => '＋'],
    ];
}

/**
 * États possibles de l’animal.
 *
 * @return array<string, string>
 */
function rv_decl_statuses(): array {
    return [
        'mort'   => 'Mort',
        'blessé' => 'Blessé',
    ];
}
