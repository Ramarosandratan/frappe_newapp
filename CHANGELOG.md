# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Non publié]

### Ajouté
- Documentation complète du projet (README.md, CONTRIBUTING.md)
- Nettoyage et organisation des fichiers de test
- Structure d'archive pour les scripts de développement

### Modifié
- Amélioration du fichier .gitignore
- Organisation des tests dans des dossiers appropriés

## [1.0.0] - 2024-01-XX

### Ajouté
- **Système de gestion de paie complet**
  - Génération automatique des bulletins de salaire
  - Calcul des composants salariaux (base, primes, déductions)
  - Support des formules personnalisées
  - Génération PDF des bulletins

- **Intégration ERPNext**
  - Authentification SSO avec ERPNext
  - Synchronisation bidirectionnelle des données
  - Gestion des documents ERPNext (soumission automatique)
  - API REST complète

- **Gestion des employés**
  - Interface de liste et détail des employés
  - Synchronisation avec ERPNext
  - Gestion des informations personnelles et professionnelles

- **Import de données**
  - Import CSV d'employés avec validation
  - Import de structures salariales
  - Gestion des dépendances automatiques
  - Interface de confirmation et gestion d'erreurs

- **Statistiques et rapports**
  - Tableaux de bord interactifs avec Chart.js
  - Graphiques de répartition salariale
  - Rapports mensuels et annuels
  - Statistiques par département et période

- **Système d'historique**
  - Suivi complet des modifications
  - Historique par entité et par utilisateur
  - Interface de consultation des changements
  - Statistiques d'utilisation

- **Modificateur de salaire**
  - Interface pour ajuster les salaires
  - Gestion des pourcentages mensuels
  - Validation et sauvegarde des modifications
  - Intégration avec le générateur de paie

- **Interface utilisateur moderne**
  - Design responsive avec Bootstrap
  - Interface intuitive et accessible
  - Composants Stimulus pour l'interactivité
  - Thème sombre/clair

### Technique
- **Framework** : Symfony 7.3
- **Base de données** : MySQL/MariaDB avec Doctrine ORM
- **Frontend** : Twig, Stimulus, Bootstrap
- **Tests** : PHPUnit avec couverture complète
- **Build** : Webpack Encore pour les assets
- **PDF** : KnpSnappyBundle (wkhtmltopdf)
- **CSV** : League CSV pour l'import/export
- **Graphiques** : Chart.js avec Symfony UX

### Sécurité
- Authentification déléguée à ERPNext
- Protection CSRF sur tous les formulaires
- Validation des données d'entrée
- Gestion sécurisée des sessions

### Performance
- Cache des requêtes Doctrine
- Optimisation des requêtes API ERPNext
- Lazy loading des relations
- Compression des assets

## Types de changements
- `Ajouté` pour les nouvelles fonctionnalités
- `Modifié` pour les changements dans les fonctionnalités existantes
- `Déprécié` pour les fonctionnalités qui seront supprimées
- `Supprimé` pour les fonctionnalités supprimées
- `Corrigé` pour les corrections de bugs
- `Sécurité` pour les vulnérabilités corrigées