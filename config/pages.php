<?php
declare(strict_types=1);

/**
 * Declares editable text blocks per static page.
 * Each block: key => [label, type (text|textarea), default]
 * The scaffolding in Plan 05 may override this file per project.
 */
return [
    'home' => [
        'hero_title'      => ['label' => 'Titre hero',       'type' => 'text',     'default' => 'Bienvenue'],
        'hero_subtitle'   => ['label' => 'Sous-titre hero',  'type' => 'text',     'default' => 'Site en construction'],
        'cta_label'       => ['label' => 'Texte bouton CTA', 'type' => 'text',     'default' => 'Nous contacter'],
        'intro_paragraph' => ['label' => 'Paragraphe intro', 'type' => 'textarea', 'default' => ''],
    ],
    'about' => [
        'intro_title'  => ['label' => 'Titre',             'type' => 'text',     'default' => 'À propos'],
        'intro_text'   => ['label' => 'Texte intro',       'type' => 'textarea', 'default' => ''],
        'values_block' => ['label' => 'Nos valeurs',       'type' => 'textarea', 'default' => ''],
    ],
    'contact' => [
        'intro_text'   => ['label' => 'Texte intro',       'type' => 'textarea', 'default' => 'N\'hésitez pas à nous contacter.'],
    ],
    'legal' => [
        'editor_name'   => ['label' => 'Éditeur (nom)',    'type' => 'text',     'default' => ''],
        'editor_info'   => ['label' => 'Éditeur (adresse, SIRET…)', 'type' => 'textarea', 'default' => ''],
        'hosting_info'  => ['label' => 'Hébergeur',        'type' => 'textarea', 'default' => ''],
    ],
];
