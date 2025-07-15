# Guide de dépannage - Pourcentages mensuels et soumission des fiches de paie

## Problèmes courants et solutions

### 1. Erreur de soumission des fiches de paie

**Symptômes :**
- Les fiches de paie sont modifiées mais ne peuvent pas être soumises
- Erreurs de type "ValidationError" ou "TimestampMismatchError"
- Incohérences entre les totaux et les composants individuels

**Causes possibles :**
- Totaux incohérents après application des pourcentages mensuels
- Problèmes de synchronisation avec ERPNext
- Valeurs négatives ou invalides

**Solutions appliquées :**

#### A. Validation des données avant sauvegarde
```php
// Nouvelle méthode validateSalarySlipData() dans ErpNextService
// - Vérifie la cohérence des totaux
// - Corrige automatiquement les incohérences
// - Valide les champs obligatoires
```

#### B. Validation des totaux dans le contrôleur
```php
// Nouvelle méthode validateSalarySlipTotals() dans SalaryModifierController
// - Recalcule et vérifie tous les totaux
// - Applique une tolérance de 0.01 pour les arrondis
// - Corrige automatiquement les écarts
```

#### C. Amélioration de l'application des pourcentages
```php
// Méthode applyMonthlyPercentage() améliorée
// - Validation des paramètres d'entrée
// - Limitation des pourcentages extrêmes (-100% à +1000%)
// - Gestion des valeurs négatives
// - Arrondi à 2 décimales
```

### 2. Pourcentages non appliqués correctement

**Symptômes :**
- Les pourcentages ne sont pas appliqués aux bonnes fiches
- Calculs incorrects
- Valeurs inattendues

**Solutions :**

#### A. Validation du mois de la fiche de paie
```php
// Extraction correcte du mois depuis start_date
$slipDate = new \DateTime($slip['start_date']);
$slipMonth = (int) $slipDate->format('n'); // n = mois sans zéro initial
```

#### B. Gestion des cas limites
- Mois invalides (< 1 ou > 12) → utilise la valeur de base
- Valeurs négatives → utilise la valeur de base
- Pourcentages extrêmes → limitation automatique

### 3. Erreurs de base de données

**Symptômes :**
- Erreurs lors de la sauvegarde des pourcentages
- Pourcentages non récupérés correctement

**Solutions :**

#### A. Gestion des erreurs dans MonthlyPercentageService
```php
// Méthode saveMonthlyPercentages() avec try-catch
// Suppression des anciens pourcentages avant insertion
// Validation des données avant sauvegarde
```

### 4. Problèmes de performance

**Symptômes :**
- Lenteur lors de l'application des pourcentages
- Timeouts

**Solutions :**

#### A. Optimisation des requêtes
- Utilisation de `saveOrUpdate()` au lieu de multiples requêtes
- Suppression en lot des anciens pourcentages

#### B. Gestion des erreurs avec continue
```php
// En cas d'erreur sur une fiche, continuer avec les suivantes
catch (\Exception $updateException) {
    $this->logger->error("Failed to update salary slip");
    $errorCount++;
    continue; // Passer à la fiche suivante
}
```

## Tests de validation

### Exécuter les tests
```bash
php test_monthly_percentages.php
```

### Tests inclus :
1. Sauvegarde des pourcentages mensuels
2. Récupération des pourcentages
3. Application des pourcentages (tous les mois)
4. Gestion des cas limites (mois invalides, valeurs négatives)
5. Pourcentages extrêmes
6. Vérification de la cohérence des calculs

## Logs de débogage

### Activer les logs détaillés
Les logs sont automatiquement générés dans :
- `var/log/dev.log` (environnement de développement)
- Rechercher les entrées avec "monthly percentage" ou "salary slip"

### Informations loggées :
- Application des pourcentages mensuels
- Validation des totaux
- Erreurs de soumission
- Corrections automatiques

## Vérifications manuelles

### 1. Vérifier les pourcentages en base
```sql
SELECT * FROM monthly_percentages WHERE component = 'Salaire de base';
```

### 2. Vérifier la cohérence d'une fiche de paie
```php
// Dans le contrôleur, avant sauvegarde
$this->logger->info("Salary slip totals", [
    'gross_pay' => $slip['gross_pay'],
    'calculated_earnings' => array_sum(array_column($slip['earnings'], 'amount')),
    'total_deduction' => $slip['total_deduction'],
    'calculated_deductions' => array_sum(array_column($slip['deductions'], 'amount')),
    'net_pay' => $slip['net_pay']
]);
```

## Recommandations

1. **Toujours tester** avec un petit échantillon avant d'appliquer sur toutes les fiches
2. **Vérifier les logs** après chaque opération
3. **Sauvegarder** les données avant modifications importantes
4. **Utiliser des pourcentages raisonnables** (éviter les valeurs extrêmes)
5. **Vérifier la cohérence** des totaux après modification

## Contact support

En cas de problème persistant :
1. Consulter les logs détaillés
2. Exécuter le script de test
3. Vérifier la cohérence des données en base
4. Fournir les logs d'erreur complets