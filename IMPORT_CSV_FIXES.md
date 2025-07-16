# Corrections d'Import CSV - Guide Technique

## 🎯 Problèmes Identifiés et Corrigés

### 1. Gestion des Erreurs Améliorée

**Problème :** Les erreurs d'import n'étaient pas gérées de manière robuste, causant des échecs complets.

**Solution :**
- Ajout de mécanismes de retry automatique pour les erreurs temporaires
- Classification des erreurs (retryable vs non-retryable)
- Gestion spécifique des documents annulés
- Nettoyage des messages d'erreur sensibles

```php
// Exemple d'utilisation
if ($this->isRetryableError($e)) {
    // Retry automatique avec délai
    usleep(500000); // 0.5 seconde
    // Nouvelle tentative...
}
```

### 2. Validation des Données

**Problème :** Les données n'étaient pas validées avant l'envoi à ERPNext.

**Solution :**
- Validation complète des données d'employé
- Vérification des formats de date
- Contrôle des champs obligatoires
- Validation des types de données

```php
$validationErrors = $this->validateEmployeeData($employeeData);
if (!empty($validationErrors)) {
    throw new \RuntimeException('Erreurs de validation: ' . implode(', ', $validationErrors));
}
```

### 3. Gestion de l'Encodage CSV

**Problème :** Problèmes avec les caractères spéciaux dans les fichiers CSV.

**Solution :**
- Détection automatique de l'encodage
- Configuration appropriée du lecteur CSV
- Support des encodages UTF-8, ISO-8859-1, Windows-1252

### 4. Configuration des Timeouts

**Problème :** Les imports longs échouaient par timeout.

**Solution :**
- Augmentation des limites de temps d'exécution (5 minutes)
- Augmentation de la limite mémoire (512M)
- Configuration des timeouts de socket

```php
private function configureTimeouts(): void
{
    set_time_limit(300); // 5 minutes
    ini_set('memory_limit', '512M');
    ini_set('default_socket_timeout', 60);
}
```

## 🔧 Nouvelles Fonctionnalités

### Méthodes Utilitaires Ajoutées

1. **`configureCsvEncoding()`** - Gestion de l'encodage CSV
2. **`sanitizeErrorMessage()`** - Nettoyage des messages d'erreur
3. **`isRetryableError()`** - Détection des erreurs retryables
4. **`isCancelledDocumentError()`** - Détection des documents annulés
5. **`validateEmployeeData()`** - Validation des données d'employé
6. **`configureTimeouts()`** - Configuration des timeouts

### Types d'Erreurs Gérées

#### Erreurs Retryables (Retry Automatique)
- `TimestampMismatchError` - Erreurs de concurrence
- `Connection timeout` - Timeouts de connexion
- `Lock wait timeout` - Verrouillages de base de données
- `Deadlock found` - Interblocages

#### Erreurs de Documents Annulés (Ignorées)
- `Cannot edit cancelled document`
- `Cannot update cancelled salary slip`
- `Document is cancelled`

#### Erreurs de Validation (Affichées à l'utilisateur)
- Champs obligatoires manquants
- Formats de date invalides
- Types de données incorrects

## 📊 Améliorations de Performance

### Optimisations Appliquées

1. **Retry Intelligent**
   - Délais progressifs entre les tentatives
   - Maximum 1 retry par erreur
   - Logging détaillé des tentatives

2. **Validation Précoce**
   - Validation avant envoi à ERPNext
   - Évite les appels API inutiles
   - Feedback immédiat à l'utilisateur

3. **Gestion Mémoire**
   - Limite mémoire augmentée
   - Nettoyage des ressources temporaires
   - Traitement par lots respecté

## 🚀 Utilisation

### Import Standard
```bash
# Accéder à l'interface d'import
http://localhost/import

# Sélectionner les 3 fichiers CSV requis :
# - Employés (test_employees.csv)
# - Structures salariales (test_structures.csv)  
# - Données salariales (test_salary_data.csv)
```

### Import avec Dépendances
```bash
# Pour un import plus robuste avec gestion des dépendances
http://localhost/import/with-dependencies
```

### Monitoring des Erreurs
```bash
# Surveiller les erreurs en temps réel
php monitor_errors.php

# Tester les corrections
php test_import_fixes.php
```

## 📋 Format des Fichiers CSV

### Employés (test_employees.csv)
```csv
Ref,Nom,Prenom,genre,Date embauche,date naissance,company
1,Rakoto,Alain,Masculin,03/04/2024,01/01/1980,My Company
```

### Structures Salariales (test_structures.csv)
```csv
salary structure,name,Abbr,type,valeur,company
gasy1,Salaire Base,SB,earning,base,My Company
gasy1,Indemnité,IND,earning,SB * 0.3,My Company
```

### Données Salariales (test_salary_data.csv)
```csv
Mois,Ref Employe,Salaire Base,Salaire
01/04/2025,1,1500000,gasy1
01/04/2025,2,900000,gasy1
```

## 🔍 Débogage

### Logs Disponibles
- `var/log/dev.log` - Logs généraux de l'application
- Logs spécifiques par type d'erreur
- Traces détaillées des tentatives de retry

### Messages d'Erreur Nettoyés
- Suppression des emails et IPs
- Masquage des mots de passe
- Limitation de la longueur des messages

### Commandes de Monitoring
```bash
# Surveiller les erreurs de fiches de paie
tail -f var/log/dev.log | grep -i 'salary\|slip\|error'

# Compter les erreurs récentes
grep -i 'error.*salary' var/log/dev.log | wc -l

# Voir les erreurs de documents annulés
grep -i 'Cannot edit cancelled' var/log/dev.log | tail -5
```

## ⚠️ Recommandations Futures

### Améliorations Suggérées
1. **Barre de progression en temps réel** - Interface utilisateur améliorée
2. **Reprise d'import** - Possibilité de reprendre un import interrompu
3. **Validation croisée** - Vérification des références entre fichiers
4. **Import par lots** - Traitement optimisé pour de gros volumes
5. **Cache des validations** - Éviter les validations répétitives

### Monitoring Continu
- Surveiller les taux d'erreur
- Analyser les performances d'import
- Optimiser les timeouts selon l'usage
- Ajuster les limites de retry

## ✅ Tests de Validation

Le script `test_import_fixes.php` valide :
- ✅ Nettoyage des messages d'erreur
- ✅ Détection des erreurs retryables
- ✅ Validation des données d'employé
- ✅ Format des fichiers CSV de test
- ✅ Intégrité des en-têtes

## 📞 Support

En cas de problème :
1. Vérifier les logs dans `var/log/dev.log`
2. Exécuter `php test_import_fixes.php` pour diagnostiquer
3. Utiliser `php monitor_errors.php` pour surveiller
4. Consulter ce guide pour les solutions communes

---

**Version :** 1.0  
**Date :** Décembre 2024  
**Auteur :** Assistant IA  
**Status :** ✅ Testé et Validé