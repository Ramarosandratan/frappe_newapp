# 📊 Guide de Résolution - Problèmes d'Affichage des Statistiques

## 🎯 Problème : Les fiches de paie générées ne s'affichent pas dans les statistiques

### 🔍 Causes Possibles

#### 1. **Problème de Période/Dates**
- Les fiches ont été générées pour une période différente de celle affichée
- Les filtres de dates ne correspondent pas exactement
- Décalage entre les dates de début/fin des fiches et les filtres

#### 2. **Problème de Récupération des Données**
- Méthodes différentes utilisées par les pages (HomeController vs StatsController)
- Filtres ERPNext qui ne fonctionnent pas comme attendu
- Permissions insuffisantes pour lire certaines données

#### 3. **Problème de Cache/Affichage**
- Cache du navigateur qui affiche d'anciennes données
- Erreurs JavaScript qui empêchent l'affichage
- Problème de rendu des templates Twig

## 🛠️ Solutions Étape par Étape

### Étape 1: Diagnostic des Données
```bash
# Exécuter le script de diagnostic
php debug_statistics.php
```

Ce script vérifie :
- ✅ Fiches trouvées par la méthode HomeController
- ✅ Fiches trouvées par la méthode StatsController  
- ✅ Fiches des 30 derniers jours
- ✅ Employés et structures disponibles

### Étape 2: Vérifier les Logs
```bash
# Consulter les logs Symfony
tail -f var/log/dev.log | grep -i "salary\|stats"

# Rechercher des erreurs spécifiques
grep -i "error\|exception" var/log/dev.log | grep -i "salary"
```

### Étape 3: Vérifier dans ERPNext
1. Connectez-vous à ERPNext
2. Allez dans **Paie > Fiche de paie**
3. Vérifiez que les fiches sont bien présentes
4. Notez les dates exactes (start_date, end_date)
5. Vérifiez le statut des fiches (Draft, Submitted, Cancelled)

### Étape 4: Vérifier les Permissions
1. Dans ERPNext, allez dans **Paramètres > Utilisateurs et permissions**
2. Vérifiez que l'utilisateur API a les droits :
   - `Read` sur `Salary Slip`
   - `Read` sur `Employee`
   - `Read` sur `Salary Structure`

## 🔧 Solutions Spécifiques

### Solution 1: Problème de Dates

**Si les fiches existent mais ne s'affichent pas :**

1. **Vérifiez les dates exactes dans ERPNext**
2. **Modifiez temporairement les filtres dans HomeController :**

```php
// Dans src/Controller/HomeController.php, ligne ~31
// Remplacez :
$currentMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');

// Par (pour tester) :
$currentMonth = '2024-01-01'; // Date de début de vos fiches
$endOfMonth = '2024-01-31';   // Date de fin de vos fiches
```

### Solution 2: Forcer le Rafraîchissement

1. **Vider le cache Symfony :**
```bash
php bin/console cache:clear
```

2. **Vider le cache du navigateur :**
   - Ctrl+F5 (Windows/Linux)
   - Cmd+Shift+R (Mac)
   - Ou mode navigation privée

### Solution 3: Vérifier les Erreurs JavaScript

1. **Ouvrir les outils de développement** (F12)
2. **Aller dans l'onglet Console**
3. **Rechercher des erreurs en rouge**
4. **Rafraîchir la page et noter les erreurs**

### Solution 4: Test Manuel des Méthodes

Créez un fichier `test_methods.php` :

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Service\ErpNextService;
use Symfony\Component\HttpClient\HttpClient;
use Psr\Log\NullLogger;

$erpNextService = new ErpNextService(HttpClient::create(), new NullLogger());
$erpNextService->setCredentials('http://your-erpnext-url', 'api_key', 'api_secret');

// Test méthode HomeController
$currentMonth = date('Y-m-01');
$endOfMonth = date('Y-m-t');
$slips1 = $erpNextService->getSalarySlipsByPeriod($currentMonth, $endOfMonth);
echo "HomeController method: " . count($slips1) . " slips\n";

// Test méthode StatsController
$currentYear = date('Y');
$slips2 = $erpNextService->getAllSalarySlips($currentYear);
echo "StatsController method: " . count($slips2) . " slips\n";

// Afficher quelques détails
foreach (array_slice($slips1, 0, 3) as $slip) {
    echo "Slip: " . $slip['name'] . " | " . $slip['start_date'] . " to " . $slip['end_date'] . "\n";
}
```

## 🎯 Solutions Rapides

### Solution Express 1: Modifier les Filtres de Date

Si vous savez que vos fiches ont été générées pour janvier 2024, modifiez temporairement :

```php
// Dans src/Controller/HomeController.php
$currentMonth = '2024-01-01';
$endOfMonth = '2024-01-31';
```

### Solution Express 2: Utiliser une Méthode Alternative

Remplacez dans `HomeController.php` :

```php
// Remplacez :
$currentMonthSlips = $this->erpNextService->getSalarySlipsByPeriod($currentMonth, $endOfMonth);

// Par :
$currentMonthSlips = $this->erpNextService->getSalarySlips([
    'start_date' => $currentMonth,
    'end_date' => $endOfMonth,
]);
```

### Solution Express 3: Affichage de Debug

Ajoutez temporairement dans le template `home/index.html.twig` :

```twig
<!-- Ajoutez avant la ligne 20 -->
<div class="alert alert-info">
    <strong>Debug:</strong> 
    Période recherchée: {{ currentMonth|date('Y-m-d') }} à {{ currentMonth|date('Y-m-t') }}<br>
    Fiches trouvées: {{ currentMonthSlipCount }}<br>
    {% if currentMonthSlipCount == 0 %}
        <em>Aucune fiche trouvée pour cette période. Vérifiez les dates dans ERPNext.</em>
    {% endif %}
</div>
```

## 📋 Checklist de Vérification

### ✅ Données ERPNext
- [ ] Les fiches de paie existent dans ERPNext
- [ ] Les fiches ont le statut "Submitted" (soumises)
- [ ] Les dates correspondent à la période recherchée
- [ ] L'utilisateur API a les permissions de lecture

### ✅ Application Symfony
- [ ] Aucune erreur dans les logs (`var/log/dev.log`)
- [ ] Le script de diagnostic trouve les fiches
- [ ] Les méthodes de service retournent des données
- [ ] Le cache a été vidé

### ✅ Interface Utilisateur
- [ ] Aucune erreur JavaScript dans la console
- [ ] Le cache du navigateur a été vidé
- [ ] La page se charge complètement
- [ ] Les templates Twig s'affichent correctement

## 🆘 Si Rien ne Fonctionne

1. **Exécutez le diagnostic complet :**
```bash
php debug_statistics.php > diagnostic_results.txt
```

2. **Collectez les logs :**
```bash
tail -100 var/log/dev.log > recent_logs.txt
```

3. **Vérifiez la configuration ERPNext :**
   - URL d'accès correcte
   - Credentials API valides
   - Permissions utilisateur
   - Statut des fiches de paie

4. **Testez avec des dates fixes :**
   - Modifiez temporairement les dates dans le code
   - Utilisez les dates exactes de vos fiches ERPNext

---

## 💡 Conseil Principal

**Le problème le plus courant est un décalage de dates.** Les fiches sont générées pour une période (ex: 01/01/2024 - 31/01/2024) mais les statistiques cherchent une autre période (ex: mois en cours).

**Solution rapide :** Vérifiez les dates exactes dans ERPNext et ajustez les filtres en conséquence.

**🎯 Dans 90% des cas, c'est un problème de dates ou de permissions !**