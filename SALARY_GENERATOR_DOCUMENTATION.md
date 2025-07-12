# 📊 Documentation - Génération Automatique de Salaire

## Vue d'ensemble

Le système de génération automatique de salaire permet de créer en masse les fiches de paie pour tous les employés actifs sur une période donnée. Il offre plusieurs options pour personnaliser la génération selon les besoins.

## ✅ Fonctionnalités Implémentées

### 🎯 Contrôleur - `SalaryGeneratorController`
- ✅ Route `/salary/generator` configurée
- ✅ Formulaire de génération avec validation
- ✅ Gestion des messages de retour (succès, erreurs, avertissements)
- ✅ Redirection après traitement

### 📝 Formulaire - `SalaryGeneratorType`
- ✅ **Date début / fin** : Champs obligatoires avec validation
- ✅ **Salaire de base** : Champ optionnel en euros
- ✅ **Case écrasement** : Option pour remplacer les fiches existantes
- ✅ **Case moyenne** : Option pour utiliser la moyenne des salaires précédents
- ✅ Validation côté client avec JavaScript
- ✅ Interface utilisateur intuitive avec Bootstrap

### 🔧 Service - `SalaryGeneratorService`
- ✅ **Récupération du dernier salaire** avant la date de début
- ✅ **Vérification d'existence** des salaires sur la période
- ✅ **Suppression automatique** des fiches existantes (mode écrasement)
- ✅ **Génération via API ERPNext** avec gestion d'erreurs
- ✅ **Calcul de moyenne** des 3 derniers salaires
- ✅ **Gestion des structures salariales** automatique
- ✅ **Logging complet** pour le débogage

## 🚀 Utilisation

### Interface Web
1. Accédez à `/salary/generator`
2. Remplissez le formulaire :
   - **Date début** : Date de début de la période
   - **Date fin** : Date de fin de la période
   - **Salaire de base** (optionnel) : Montant fixe à utiliser
   - **Écraser les existantes** : Cochez pour remplacer les fiches existantes
   - **Utiliser la moyenne** : Cochez pour calculer la moyenne des salaires précédents
3. Cliquez sur "Générer les fiches de paie"

### Logique de Génération

#### 📅 Période de Génération
- La période est définie par les dates de début et fin
- Le système vérifie l'existence de fiches pour cette période
- Sans l'option "écrasement", les fiches existantes sont ignorées
- Avec l'option "écrasement", les fiches existantes sont **supprimées puis recréées**

#### 💰 Détermination du Salaire de Base

**Priorité 1 - Salaire spécifié :**
- Si un montant est saisi dans le champ "Salaire de base"
- Ce montant est utilisé pour tous les employés
- Les composants (primes, déductions) sont copiés du dernier salaire

**Priorité 2 - Option moyenne activée :**
- Calcule la moyenne des 3 dernières fiches de paie validées
- Utilise cette moyenne comme salaire de base
- Les composants sont copiés du dernier salaire

**Priorité 3 - Dernier salaire connu :**
- Recherche la dernière fiche de paie avant la date de début
- Utilise le salaire de base et tous les composants de cette fiche
- Si aucune fiche précédente, utilise le salaire de la structure assignée

#### 🏗️ Gestion des Structures Salariales
- Vérifie l'assignation de structure salariale pour chaque employé
- Assigne automatiquement une structure si nécessaire
- Utilise la première structure disponible par défaut

## 📊 Retour d'Information

### Messages de Succès
- ✅ Nombre de fiches créées avec succès
- ℹ️ Nombre de fiches ignorées (déjà existantes)
- 🗑️ Nombre de fiches supprimées avant recréation

### Messages d'Erreur
- ❌ Erreurs spécifiques par employé
- ⚠️ Avertissements généraux (aucune fiche générée)

### Logging
- Logs détaillés dans les fichiers de log Symfony
- Informations sur chaque étape du processus
- Erreurs avec stack traces pour le débogage

## 🧪 Tests

### Tests Unitaires - `SalaryGeneratorServiceTest`
- ✅ Test avec salaire de base spécifique
- ✅ Test d'ignorance des fiches existantes
- ✅ Test d'écrasement des fiches existantes
- ✅ Test d'utilisation du dernier salaire
- ✅ Test d'utilisation de la moyenne

### Exécution des Tests
```bash
php bin/phpunit tests/Service/SalaryGeneratorServiceTest.php
```

## 🔧 Configuration Technique

### Dépendances
- `ErpNextService` : Communication avec l'API ERPNext
- `LoggerInterface` : Logging des opérations
- Symfony Form Component : Gestion des formulaires
- Symfony Validator : Validation des données

### Méthodes API ERPNext Utilisées
- `getActiveEmployees()` : Récupération des employés actifs
- `getSalarySlips()` : Vérification des fiches existantes
- `getSalarySlipsForEmployee()` : Historique des salaires
- `getSalarySlipDetails()` : Détails d'une fiche de paie
- `getEmployeeSalaryStructureAssignment()` : Assignation de structure
- `getSalaryStructures()` : Structures disponibles
- `assignSalaryStructure()` : Assignation automatique
- `addSalarySlip()` : Création de fiche de paie
- `deleteSalarySlip()` : Suppression d'une fiche de paie
- `deleteExistingSalarySlips()` : Suppression en lot des fiches existantes

## 🎨 Interface Utilisateur

### Fonctionnalités JavaScript
- Validation en temps réel des champs
- Désactivation du champ salaire quand "moyenne" est cochée
- Feedback visuel pour les états du formulaire
- Messages d'aide contextuels

### Responsive Design
- Interface adaptée mobile et desktop
- Utilisation de Bootstrap pour la cohérence
- Icônes Font Awesome pour l'UX

## 🚨 Gestion d'Erreurs

### Erreurs Courantes
1. **Aucun employé actif** : Vérifier les statuts dans ERPNext
2. **Pas de structure salariale** : Créer au moins une structure
3. **Problème d'API** : Vérifier la connectivité ERPNext
4. **Données manquantes** : Vérifier les champs obligatoires

### Récupération d'Erreurs
- Traitement individuel par employé
- Continuation du processus malgré les erreurs
- Rapport détaillé des succès et échecs

## 📈 Performance

### Optimisations
- Traitement par lot des employés
- Cache des structures salariales
- Réutilisation des connexions API
- Logging asynchrone

### Recommandations
- Traiter par petits groupes d'employés
- Exécuter pendant les heures creuses
- Surveiller les logs pour les performances

## 🔮 Évolutions Futures

### Améliorations Possibles
- [ ] Génération par département/équipe
- [ ] Planification automatique (cron jobs)
- [ ] Templates de génération personnalisés
- [ ] Export des résultats en CSV/Excel
- [ ] Notifications par email
- [ ] Interface de prévisualisation avant génération

### Intégrations
- [ ] Système de workflow d'approbation
- [ ] Intégration avec la comptabilité
- [ ] Synchronisation avec les systèmes RH externes
- [ ] API REST pour intégrations tierces

---

## 📞 Support

Pour toute question ou problème :
1. Consultez les logs Symfony (`var/log/`)
2. Vérifiez la connectivité ERPNext
3. Testez avec le script `test_salary_generator.php`
4. Consultez la documentation ERPNext API

**Status : ✅ COMPLÈTEMENT IMPLÉMENTÉ ET TESTÉ**