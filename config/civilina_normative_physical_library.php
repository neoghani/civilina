<?php

return [
    /*
     * Dossier physique classé.
     * On utilise volontairement un chemin court sous Windows afin d'éviter
     * les problèmes MAX_PATH du projet Civilina.
     */
    'classified_path' => env(
        'CIVILINA_NORMATIVE_CLASSIFIED_PATH',
        'D:\\Civilina_Normes_Classees'
    ),

    /*
     * hardlink = aucun doublage de poids sur le même volume NTFS.
     * En cas d'échec, l'organiseur retombe automatiquement sur copy.
     */
    'mode' => env('CIVILINA_NORMATIVE_ORGANIZE_MODE', 'hardlink'),

    'filename_max' => 125,
];
