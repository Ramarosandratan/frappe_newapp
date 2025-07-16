# Guide de Contribution

Merci de votre intérêt pour contribuer à Frappe NewApp ! Ce guide vous aidera à comprendre comment contribuer efficacement au projet.

## 🚀 Démarrage rapide

1. **Fork** le repository
2. **Clone** votre fork localement
3. **Créez** une branche pour votre fonctionnalité
4. **Développez** et testez vos modifications
5. **Soumettez** une Pull Request

## 📋 Prérequis pour le développement

- PHP 8.2+
- Composer
- Node.js et npm
- MySQL/MariaDB
- Instance ERPNext de test
- Git

## 🛠️ Configuration de l'environnement de développement

### 1. Installation
```bash
git clone https://github.com/votre-username/frappe_newapp.git
cd frappe_newapp
composer install
npm install
```

### 2. Configuration
```bash
cp .env .env.local
# Configurez vos variables d'environnement
```

### 3. Base de données
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 4. Assets
```bash
npm run watch  # Pour le développement
```

## 📝 Standards de code

### PHP
- Suivre les standards **PSR-12**
- Utiliser les **type hints** partout où c'est possible
- Documenter les méthodes publiques avec **PHPDoc**
- Respecter les conventions de nommage Symfony

### JavaScript
- Utiliser **ES6+**
- Suivre les conventions **Stimulus**
- Commenter le code complexe

### Twig
- Indentation de **4 espaces**
- Utiliser les **filtres** appropriés
- Éviter la logique complexe dans les templates

## 🧪 Tests

### Exécution des tests
```bash
# Tous les tests
php bin/phpunit

# Tests spécifiques
php bin/phpunit tests/Service/
php bin/phpunit tests/Controller/
```

### Écriture de tests
- **Tests unitaires** pour les services
- **Tests fonctionnels** pour les contrôleurs
- **Tests d'intégration** pour les workflows complets
- **Couverture minimale** de 80%

### Structure des tests
```php
<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\MonService;

class MonServiceTest extends TestCase
{
    public function testMethodeSpecifique(): void
    {
        // Arrange
        $service = new MonService();
        
        // Act
        $result = $service->methodeSpecifique();
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

## 🔄 Workflow de contribution

### 1. Créer une branche
```bash
git checkout -b feature/nouvelle-fonctionnalite
# ou
git checkout -b fix/correction-bug
# ou
git checkout -b docs/mise-a-jour-documentation
```

### 2. Développement
- Faites des **commits atomiques**
- Utilisez des **messages de commit clairs**
- Testez vos modifications

### 3. Messages de commit
Format recommandé :
```
type(scope): description courte

Description plus détaillée si nécessaire

Fixes #123
```

Types :
- `feat`: nouvelle fonctionnalité
- `fix`: correction de bug
- `docs`: documentation
- `style`: formatage, pas de changement de code
- `refactor`: refactoring
- `test`: ajout/modification de tests
- `chore`: tâches de maintenance

### 4. Pull Request
- **Titre clair** et descriptif
- **Description détaillée** des changements
- **Référence aux issues** liées
- **Screenshots** si applicable
- **Tests** passants

## 🐛 Signalement de bugs

### Avant de signaler
1. Vérifiez les **issues existantes**
2. Testez avec la **dernière version**
3. Reproduisez le bug de manière **consistante**

### Template d'issue
```markdown
## Description
Description claire du problème

## Étapes pour reproduire
1. Aller à '...'
2. Cliquer sur '...'
3. Voir l'erreur

## Comportement attendu
Ce qui devrait se passer

## Comportement actuel
Ce qui se passe réellement

## Environnement
- OS: [ex: Ubuntu 20.04]
- PHP: [ex: 8.2.1]
- Symfony: [ex: 7.3.0]
- Navigateur: [ex: Chrome 120]

## Logs/Screenshots
Ajoutez les logs d'erreur ou captures d'écran
```

## ✨ Demande de fonctionnalité

### Template de demande
```markdown
## Problème résolu
Quel problème cette fonctionnalité résout-elle ?

## Solution proposée
Description de la solution souhaitée

## Alternatives considérées
Autres solutions envisagées

## Contexte supplémentaire
Informations additionnelles, mockups, etc.
```

## 📚 Documentation

### Types de documentation
- **README.md** : Vue d'ensemble du projet
- **Code comments** : Documentation inline
- **PHPDoc** : Documentation des API
- **docs/** : Documentation technique détaillée

### Mise à jour de la documentation
- Mettez à jour la documentation avec vos changements
- Ajoutez des exemples d'utilisation
- Documentez les nouvelles API

## 🔍 Revue de code

### Checklist pour les reviewers
- [ ] Le code suit les standards du projet
- [ ] Les tests passent
- [ ] La documentation est à jour
- [ ] Pas de régression introduite
- [ ] Performance acceptable
- [ ] Sécurité respectée

### Checklist pour les contributeurs
- [ ] Code testé localement
- [ ] Tests automatisés ajoutés/mis à jour
- [ ] Documentation mise à jour
- [ ] Pas de code mort
- [ ] Variables et méthodes nommées clairement
- [ ] Gestion d'erreurs appropriée

## 🚀 Déploiement

### Environnements
- **dev** : Développement local
- **staging** : Tests d'intégration
- **prod** : Production

### Process de release
1. Merge dans `main`
2. Tests automatisés
3. Déploiement staging
4. Tests manuels
5. Déploiement production
6. Tag de version

## 📞 Communication

### Canaux
- **GitHub Issues** : Bugs et fonctionnalités
- **Pull Requests** : Revues de code
- **Discussions** : Questions générales

### Bonnes pratiques
- Soyez **respectueux** et **constructif**
- Posez des **questions claires**
- Fournissez du **contexte**
- Soyez **patient** pour les réponses

## 🏆 Reconnaissance

Les contributeurs sont reconnus dans :
- Le fichier `CONTRIBUTORS.md`
- Les notes de release
- La documentation du projet

Merci pour vos contributions ! 🎉