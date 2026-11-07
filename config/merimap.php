<?php

return [
    /*
     * Inscription libre depuis la page de connexion.
     * false (par défaut) : outil interne, les comptes sont créés par un administrateur
     * avec « php artisan merimap:user ». Les routes /register répondent alors 404.
     */
    'registration' => (bool) env('MERIMAP_REGISTRATION', false),
];
