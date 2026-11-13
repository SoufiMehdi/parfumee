# 0005 — Suivre la dernière version stable de Symfony (8.x) plutôt que la LTS

- **Statut :** Acceptée
- **Date :** 2026-11-13

## Contexte
- L'API tournait en Symfony 7.4, qui est la LTS : correctifs de bugs jusqu'en novembre 2028, de sécurité jusqu'en novembre 2029.
- Le projet est en phase 0. Il n'est pas encore en production, et l'image Docker de l'API n'existe pas encore (P7-01).
- On a choisi de passer à la dernière version stable, Symfony 8.1, plutôt que de rester sur la LTS.

## Décision
- L'API suit la **dernière version mineure stable** de Symfony 8.x : 8.1 au 2026-11-13.
- Le minimum passe à **PHP ≥ 8.4**, comme l'exige Symfony 8.
- DoctrineBundle passe de 2.18 à 3.4. Sa config est nettoyée : les objets lazy natifs (PHP 8.4) sont toujours actifs, et les options `proxy_dir`, `auto_generate_proxy_classes`, `alias`, `use_savepoints` et `report_fields_where_declared` sont retirées.

## Conséquences
- **Positif :**
  - on a les dernières fonctionnalités ;
  - il n'y aura pas de migration majeure 7 → 8 à faire plus tard, en pleine production ;
  - les dépendances sont à jour : `composer audit` ne remonte plus aucune vulnérabilité, contre 45 advisories avant.
- **Négatif :** une version non-LTS n'est maintenue que **8 mois**, d'où une montée de version mineure tous les 6 mois :
  - 8.1 : fin de maintenance en janvier 2027 ;
  - 8.2 : sortie prévue fin novembre 2026 ;
  - 8.3 : mai 2027 ;
  - 8.4 LTS : novembre 2027.
- Chaque montée de version suit la même procédure :
  - `debug:container --deprecations` doit être vide en dev, test et prod (avec `APP_DEBUG=1`) ;
  - ensuite, mettre à jour les contraintes `8.x.*` dans `composer.json` et dans `extra.symfony.require` ;
  - `composer update -W` ;
  - la suite PHPUnit doit passer (elle échoue déjà sur toute dépréciation).
- L'image FrankenPHP de prod (P7-01) doit être en PHP 8.4 au minimum. PHP 8.5 est recommandé, c'est la version utilisée en dev.
- On pourra se poser sur 8.4 LTS en novembre 2027, au moment du lancement ou après.

## Alternatives écartées
- **Rester en 7.4 LTS :** moins d'entretien, mais la migration vers 8.x reste à faire plus tard, et on se prive des nouveautés pendant la phase de développement, qui est le moment où une montée de version coûte le moins cher.
