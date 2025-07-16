# Tests - Frappe NewApp

Ce dossier contient tous les tests automatisés du projet, organisés par type et responsabilité.

## 📁 Structure

```
tests/
├── Controller/          # Tests des contrôleurs web
├── Service/            # Tests unitaires des services
├── Integration/        # Tests d'intégration
├── Functional/         # Tests fonctionnels
├── E2E/               # Tests end-to-end
└── bootstrap.php      # Configuration des tests
```

## 🧪 Types de tests

### Tests unitaires (Service/)
Tests isolés des services métier sans dépendances externes.

**Exemple** :
```php
// tests/Service/SalaryGeneratorServiceTest.php
public function testCalculateSalary(): void
{
    $service = new SalaryGeneratorService();
    $result = $service->calculateSalary($employee, $structure);
    $this->assertEquals(5000, $result->getAmount());
}
```

### Tests de contrôleurs (Controller/)
Tests des contrôleurs web avec simulation des requêtes HTTP.

**Exemple** :
```php
// tests/Controller/StatsControllerTest.php
public function testStatsIndex(): void
{
    $client = static::createClient();
    $client->request('GET', '/stats');
    $this->assertResponseIsSuccessful();
}
```

### Tests d'intégration (Integration/)
Tests des interactions entre composants avec base de données.

**Exemple** :
```php
// tests/Integration/MonthlyPercentageIntegrationTest.php
public function testSaveAndRetrievePercentage(): void
{
    // Test complet avec base de données
}
```

### Tests fonctionnels (Functional/)
Tests des fonctionnalités complètes du point de vue utilisateur.

**Exemple** :
```php
// tests/Functional/SalaryModifierControllerTest.php
public function testModifySalaryWorkflow(): void
{
    // Test du workflow complet de modification
}
```

### Tests E2E (E2E/)
Tests end-to-end simulant l'utilisation réelle de l'application.

**Exemple** :
```php
// tests/E2E/ChartE2ETest.php
public function testChartDisplayAndInteraction(): void
{
    // Test complet avec interface utilisateur
}
```

## 🚀 Exécution des tests

### Tous les tests
```bash
php bin/phpunit
```

### Par catégorie
```bash
# Tests unitaires
php bin/phpunit tests/Service/

# Tests de contrôleurs
php bin/phpunit tests/Controller/

# Tests d'intégration
php bin/phpunit tests/Integration/

# Tests fonctionnels
php bin/phpunit tests/Functional/

# Tests E2E
php bin/phpunit tests/E2E/
```

### Test spécifique
```bash
php bin/phpunit tests/Service/SalaryGeneratorServiceTest.php
```

### Avec couverture
```bash
php bin/phpunit --coverage-html coverage/
```

## 📊 Couverture de code

### Objectifs
- **Services** : 90%+ de couverture
- **Contrôleurs** : 80%+ de couverture
- **Global** : 85%+ de couverture

### Génération du rapport
```bash
php bin/phpunit --coverage-html coverage/
# Ouvrir coverage/index.html dans le navigateur
```

## 🛠️ Configuration

### PHPUnit (phpunit.dist.xml)
Configuration principale des tests avec :
- Bootstrap des tests
- Répertoires de tests
- Configuration de la couverture
- Variables d'environnement de test

### Bootstrap (bootstrap.php)
Initialisation de l'environnement de test :
- Chargement de l'autoloader
- Configuration de l'environnement de test
- Initialisation des services de test

## 📝 Bonnes pratiques

### Nommage
- **Classes** : `NomServiceTest.php`
- **Méthodes** : `testMethodeSpecifique()`
- **Données** : `provideDataForTest()`

### Structure des tests
```php
public function testMethode(): void
{
    // Arrange - Préparation
    $service = new MonService();
    $input = 'test-data';
    
    // Act - Action
    $result = $service->methode($input);
    
    // Assert - Vérification
    $this->assertEquals('expected', $result);
}
```

### Mocking
```php
public function testWithMock(): void
{
    $mock = $this->createMock(DependencyInterface::class);
    $mock->expects($this->once())
         ->method('method')
         ->willReturn('mocked-result');
    
    $service = new MonService($mock);
    $result = $service->methodWithDependency();
    
    $this->assertEquals('expected', $result);
}
```

### Data Providers
```php
/**
 * @dataProvider salaryDataProvider
 */
public function testSalaryCalculation(int $base, float $rate, int $expected): void
{
    $result = $this->calculator->calculate($base, $rate);
    $this->assertEquals($expected, $result);
}

public function salaryDataProvider(): array
{
    return [
        'basic salary' => [5000, 1.0, 5000],
        'with bonus' => [5000, 1.2, 6000],
        'with deduction' => [5000, 0.8, 4000],
    ];
}
```

## 🐛 Debugging des tests

### Tests qui échouent
```bash
# Mode verbose
php bin/phpunit --verbose

# Arrêt au premier échec
php bin/phpunit --stop-on-failure

# Debug d'un test spécifique
php bin/phpunit --filter testMethodeSpecifique
```

### Variables d'environnement
```bash
# Environnement de test
APP_ENV=test php bin/phpunit

# Base de données de test
DATABASE_URL="sqlite:///:memory:" php bin/phpunit
```

## 📚 Ressources

- [Documentation PHPUnit](https://phpunit.de/documentation.html)
- [Tests Symfony](https://symfony.com/doc/current/testing.html)
- [Mocking avec PHPUnit](https://phpunit.de/manual/current/en/test-doubles.html)

## 🤝 Contribution

Lors de l'ajout de nouvelles fonctionnalités :
1. **Écrivez les tests en premier** (TDD)
2. **Maintenez la couverture** au niveau requis
3. **Documentez les cas complexes**
4. **Utilisez des noms explicites**

Les tests sont essentiels pour maintenir la qualité et la stabilité du projet ! 🧪✨