# Système d'Historique des Modifications

## Vue d'ensemble

Le système d'historique des modifications permet de tracer toutes les modifications effectuées dans l'application, incluant :
- Les modifications de fiches de paie
- Les changements de pourcentages mensuels
- Les modifications d'employés
- Toute autre modification dans le système

## Structure de la base de données

### Table `change_history`

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INT | Identifiant unique |
| `entity_type` | VARCHAR(100) | Type d'entité (Salary Slip, Employee, etc.) |
| `entity_id` | VARCHAR(255) | Identifiant de l'entité modifiée |
| `field_name` | VARCHAR(100) | Nom du champ modifié |
| `old_value` | LONGTEXT | Ancienne valeur |
| `new_value` | LONGTEXT | Nouvelle valeur |
| `action` | VARCHAR(50) | Type d'action (CREATE, UPDATE, DELETE) |
| `user_id` | VARCHAR(100) | Identifiant de l'utilisateur |
| `user_name` | VARCHAR(255) | Nom de l'utilisateur |
| `changed_at` | DATETIME | Date et heure de la modification |
| `ip_address` | VARCHAR(45) | Adresse IP de l'utilisateur |
| `user_agent` | LONGTEXT | User-Agent du navigateur |
| `metadata` | JSON | Métadonnées supplémentaires |
| `reason` | LONGTEXT | Raison de la modification |

## Utilisation du service

### Service `ChangeHistoryService`

Le service principal pour enregistrer les modifications :

```php
use App\Service\ChangeHistoryService;

// Injection du service
public function __construct(
    private ChangeHistoryService $changeHistoryService
) {}

// Enregistrer une modification générique
$this->changeHistoryService->logChange(
    'Salary Slip',           // Type d'entité
    'SAL-2024-001',         // ID de l'entité
    'base_salary',          // Champ modifié
    2500.00,                // Ancienne valeur
    2800.00,                // Nouvelle valeur
    'UPDATE',               // Action
    'Augmentation de salaire' // Raison
);

// Méthodes spécialisées
$this->changeHistoryService->logPayslipChange($id, $field, $oldValue, $newValue, $reason);
$this->changeHistoryService->logEmployeeChange($id, $field, $oldValue, $newValue, $reason);
$this->changeHistoryService->logMonthlyPercentageChange($component, $month, $oldValue, $newValue, $reason);
```

## Intégration automatique

### Dans les contrôleurs

Le système est automatiquement intégré dans :

1. **PayslipController** : Modifications de salaire de base
2. **SalaryModifierController** : Modifications en lot des composants de salaire
3. **MonthlyPercentageService** : Modifications des pourcentages mensuels

### Exemple d'intégration

```php
// Avant la modification
$oldValue = $entity->getField();

// Effectuer la modification
$entity->setField($newValue);
$this->entityManager->flush();

// Enregistrer dans l'historique
$this->changeHistoryService->logChange(
    'EntityType',
    $entity->getId(),
    'field_name',
    $oldValue,
    $newValue,
    'UPDATE',
    'Description de la modification'
);
```

## Interface utilisateur

### Pages disponibles

1. **`/history`** : Historique général de toutes les modifications
2. **`/history/entity/{type}/{id}`** : Historique d'une entité spécifique
3. **`/history/user/{userId}`** : Historique des modifications d'un utilisateur
4. **`/history/statistics`** : Statistiques des modifications
5. **`/history/export`** : Export des données d'historique

### Affichage dans les templates

L'historique est automatiquement affiché dans :
- Les pages de détail des fiches de paie
- Les pages de détail des employés (si implémenté)

## Commandes de maintenance

### Nettoyage de l'historique

```bash
# Simulation (voir ce qui serait supprimé)
php bin/console app:clean-history --dry-run

# Supprimer les modifications de plus de 365 jours
php bin/console app:clean-history

# Supprimer les modifications de plus de 90 jours
php bin/console app:clean-history 90
```

### Test du système

```bash
# Créer des données de test
php bin/console app:test-history
```

## Sécurité et performance

### Indexation

La table dispose d'index sur :
- `entity_type` et `entity_id` (recherche par entité)
- `changed_at` (tri chronologique)
- `user_id` (recherche par utilisateur)

### Nettoyage automatique

Il est recommandé de configurer une tâche cron pour nettoyer automatiquement l'historique :

```bash
# Tous les mois, supprimer les modifications de plus d'un an
0 2 1 * * /usr/bin/php /path/to/app/bin/console app:clean-history 365
```

### Limitation des données

- Les valeurs sont stockées en texte brut
- Les valeurs numériques sont formatées automatiquement dans l'interface
- Les métadonnées JSON permettent de stocker des informations contextuelles

## Exemples d'utilisation

### 1. Modification de fiche de paie

```php
// Dans PayslipController::updateBaseSalary()
$this->changeHistoryService->logPayslipChange(
    $payslipId,
    'base_salary',
    $oldBaseSalary,
    $newBaseSalary,
    'Modification du salaire de base via l\'interface web'
);
```

### 2. Modification en lot

```php
// Dans SalaryModifierController
foreach ($modifiedSlips as $slip) {
    $this->changeHistoryService->logPayslipChange(
        $slip['name'],
        $component,
        $oldValue,
        $newValue,
        "Modification par condition ({$condition} {$conditionValue})"
    );
}
```

### 3. Pourcentages mensuels

```php
// Lors de la sauvegarde des pourcentages
$this->changeHistoryService->logMonthlyPercentageChange(
    $component,
    $month,
    $oldPercentage,
    $newPercentage,
    'Modification des pourcentages mensuels via l\'interface web'
);
```

## Dépannage

### Problèmes courants

1. **Service non injecté** : Vérifier que `ChangeHistoryService` est bien injecté dans le constructeur
2. **Données manquantes** : Vérifier que la migration a été exécutée
3. **Performance** : Utiliser les index et limiter les requêtes avec des filtres

### Logs

Les modifications sont également loggées dans les fichiers de log Symfony pour le débogage.

### Vérification des données

```sql
-- Voir les dernières modifications
SELECT * FROM change_history ORDER BY changed_at DESC LIMIT 10;

-- Statistiques par type d'entité
SELECT entity_type, action, COUNT(*) as count 
FROM change_history 
GROUP BY entity_type, action;

-- Modifications d'une entité spécifique
SELECT * FROM change_history 
WHERE entity_type = 'Salary Slip' AND entity_id = 'SAL-2024-001'
ORDER BY changed_at DESC;
```