<?php

declare(strict_types=1);

return [
    '403' => [
        'body' => 'Si vous pensez qu’il s’agit d’une erreur, connectez-vous avec le bon compte ou revenez à l’accueil.',
        'heading' => 'Vous n’avez pas accès à cette page',
    ],
    '404' => [
        'body' => 'L’article a peut-être été déplacé, son lien a pu changer, ou l’adresse a été saisie autrement. Cherchez ce que vous vouliez, ou partez d’une rubrique.',
        'heading' => 'Page introuvable',
    ],
    '419' => [
        'body' => 'Trop de temps s’est écoulé depuis l’ouverture de la page : le formulaire n’a pas pu être envoyé. Revenez en arrière, rechargez-la et réessayez.',
        'heading' => 'Cette page a expiré',
    ],
    '429' => [
        'body' => 'Nous avons reçu beaucoup de requêtes depuis cette connexion en peu de temps. Patientez une minute puis réessayez.',
        'heading' => 'Trop de requêtes',
    ],
    '500' => [
        'body' => 'Cette page n’a pas pu s’afficher à cause d’une panne du site, non de votre appareil ni de votre connexion. Réessayez dans un instant.',
        'heading' => 'Un problème est survenu de notre côté',
    ],
    '503' => [
        'body' => 'Nous effectuons une maintenance du site. Réessayez dans un instant.',
        'heading' => 'MASAR est en maintenance',
    ],
    'ui' => [
        'home' => 'Accueil',
        'kicker' => 'Erreur',
        'retry' => 'Réessayer',
        'search' => 'Rechercher dans MASAR…',
        'search_button' => 'Rechercher',
        'search_label' => 'Terme de recherche',
        'sections' => 'Rubriques',
    ],
];
