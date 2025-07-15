# Fonctionnalité des Pourcentages Mensuels

## Vue d'ensemble

Cette fonctionnalité permet d'appliquer des pourcentages d'augmentation ou de réduction de salaire différents pour chaque mois de l'année. Elle est intégrée dans le module de modification des salaires et permet une gestion plus fine des ajustements salariaux selon les périodes.

## Fonctionnalités

### 1. Interface utilisateur
- **Case à cocher** : "Utiliser des pourcentages mensuels personnalisés"
- **Champs mensuels** : 12 champs numériques pour définir le pourcentage de chaque mois
- **Chargement automatique** : Les pourcentages existants sont automatiquement chargés lors de la sélection d'un composant
- **Validation** : Désactivation du champ "Nouvelle valeur" quand les pourcentages mensuels sont activés

### 2. Stockage des données
- **Table MySQL** : `monthly_percentages`
- **Colonnes** :
  - `id` : Identifiant unique
  - `month` : Mois (1-12)
  - `percentage` : Pourcentage (DECIMAL 5,2)
  - `component` : Nom du composant de salaire
  - `created_at` : Date de création
  - `updated_at` : Date de mise à jour
- **Index** : Index unique sur (month, component) et index sur component

### 3. Logique métier
- **Application automatique** : Le pourcentage est appliqué selon le mois de la fiche de paie
- **Calcul** : `nouvelle_valeur = valeur_actuelle * (1 + pourcentage/100)`
- **Gestion des cas** : Si aucun pourcentage n'est défini pour un mois, la valeur reste inchangée
- **Persistance** : Les pourcentages sont sauvegardés et réutilisables

## Structure technique

### Entités
- `MonthlyPercentage` : Entité Doctrine pour le stockage des pourcentages

### Services
- `MonthlyPercentageService` : Service principal pour la gestion des pourcentages
  - `applyMonthlyPercentage()` : Applique le pourcentage à une valeur
  - `saveMonthlyPercentages()` : Sauvegarde les pourcentages mensuels
  - `getMonthlyPercentages()` : Récupère les pourcentages d'un composant
  - `hasMonthlyPercentages()` : Vérifie l'existence de pourcentages
  - `getMonthNames()` : Retourne les noms des mois en français

### Repository
- `MonthlyPercentageRepository` : Repository pour les opérations de base de données
  - `findByComponent()` : Trouve les pourcentages par composant
  - `findByMonthAndComponent()` : Trouve un pourcentage spécifique
  - `saveOrUpdate()` : Sauvegarde ou met à jour un pourcentage
  - `deleteByComponent()` : Supprime tous les pourcentages d'un composant

### Contrôleur
- `SalaryModifierController` : Contrôleur modifié pour intégrer les pourcentages
  - Route principale : `/salary/modifier`
  - Route AJAX : `/salary/modifier/percentages/{component}` pour récupérer les pourcentages existants

## Utilisation

### 1. Configuration des pourcentages
1. Accéder à la page "Modification des éléments de salaire"
2. Sélectionner un composant de salaire
3. Cocher "Utiliser des pourcentages mensuels personnalisés"
4. Remplir les pourcentages souhaités pour chaque mois
5. Définir les autres critères (condition, période, etc.)
6. Soumettre le formulaire

### 2. Exemples de pourcentages
- **Janvier** : +10% (prime de début d'année)
- **Février** : +5% (ajustement hivernal)
- **Mars** : -2.5% (réduction temporaire)
- **Juin** : +15% (prime de mi-année)
- **Décembre** : -5% (ajustement de fin d'année)

### 3. Application automatique
Lors du traitement des fiches de paie :
- Une fiche de janvier appliquera +10%
- Une fiche de février appliquera +5%
- Une fiche d'avril (sans pourcentage défini) restera inchangée
- Une fiche de juin appliquera +15%

## Tests

### Tests unitaires
- `MonthlyPercentageServiceTest` : Tests du service principal
- Couverture complète des méthodes du service
- Tests des cas limites et erreurs

### Tests d'intégration
- `MonthlyPercentageIntegrationTest` : Tests avec base de données
- Tests du workflow complet
- Vérification de la persistance des données

## Migration

La migration `Version20250714150425` crée :
- La table `monthly_percentages`
- Les index nécessaires pour les performances
- Les contraintes d'unicité

## Sécurité

- **Validation des entrées** : Vérification des types et valeurs
- **Index unique** : Prévention des doublons (mois, composant)
- **Logging** : Traçabilité des modifications
- **Transactions** : Cohérence des données lors des mises à jour

## Performance

- **Index optimisés** : Requêtes rapides par composant et mois
- **Chargement AJAX** : Interface réactive
- **Batch operations** : Suppression/insertion efficace lors des mises à jour

## Maintenance

### Nettoyage des données
```php
// Supprimer tous les pourcentages d'un composant
$repository->deleteByComponent('Nom du composant');
```

### Vérification des données
```php
// Vérifier l'existence de pourcentages
$hasPercentages = $service->hasMonthlyPercentages('Composant');

// Récupérer tous les pourcentages
$percentages = $service->getMonthlyPercentages('Composant');
```

## Évolutions futures possibles

1. **Interface de gestion dédiée** : Page spécifique pour gérer les pourcentages
2. **Templates de pourcentages** : Modèles prédéfinis réutilisables
3. **Historique des modifications** : Traçabilité des changements
4. **Import/Export** : Gestion en masse via fichiers CSV
5. **Validation avancée** : Règles métier plus complexes
6. **Notifications** : Alertes lors des modifications importantes