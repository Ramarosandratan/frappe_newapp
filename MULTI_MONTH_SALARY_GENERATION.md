# 📊 Génération de Salaires Multi-Mois - Guide d'Utilisation

## 🎯 Nouvelle Fonctionnalité

L'application supporte maintenant la **génération automatique de fiches de paie sur plusieurs mois** avec découpage intelligent en périodes mensuelles individuelles.

### ✨ Avantages

- **Interface simplifiée** : Sélectionnez une période globale (ex: Janvier à Mars 2024)
- **Découpage automatique** : L'application génère automatiquement une fiche par mois
- **Compatibilité ERPNext** : Respecte l'exigence d'ERPNext d'avoir une fiche par mois
- **Gestion intelligente** : Préserve les dates partielles et gère les cas limites

---

## 🚀 Comment Utiliser

### 1. Accéder au Générateur de Salaires

```
http://votre-app/salary/generator
```

### 2. Remplir le Formulaire

| Champ | Description | Exemple |
|-------|-------------|---------|
| **Date de début** | Début de la période globale | `2024-01-15` |
| **Date de fin** | Fin de la période globale | `2024-03-10` |
| **Salaire de base** | Montant fixe (optionnel) | `3200 €` |
| **Écraser les valeurs existantes** | Remplacer les fiches existantes | ☑️ |
| **Utiliser la moyenne** | Calculer la moyenne des 3 derniers salaires | ☐ |

### 3. Résultat Automatique

L'application va automatiquement :

1. **Découper la période** en mois individuels :
   ```
   Période globale : 15/01/2024 → 10/03/2024
   
   Devient :
   ├── Mois 1 : 15/01/2024 → 31/01/2024
   ├── Mois 2 : 01/02/2024 → 29/02/2024
   └── Mois 3 : 01/03/2024 → 10/03/2024
   ```

2. **Générer une fiche ERPNext** pour chaque mois
3. **Afficher un résumé** des opérations effectuées

---

## 📋 Exemples d'Utilisation

### Exemple 1 : Période de 3 Mois avec Salaire Fixe

**Configuration :**
- Période : `01/01/2024` → `31/03/2024`
- Salaire de base : `2800 €`
- Écraser : ✅
- Moyenne : ❌

**Résultat :**
- 3 fiches créées (Janvier, Février, Mars)
- Chaque fiche avec un salaire de base de 2800 €
- Composants copiés depuis la dernière fiche existante

### Exemple 2 : Période de 6 Mois avec Moyenne

**Configuration :**
- Période : `15/01/2024` → `20/06/2024`
- Salaire de base : *(vide)*
- Écraser : ❌
- Moyenne : ✅

**Résultat :**
- 6 fiches créées (si elles n'existent pas déjà)
- Salaire de base = moyenne des 3 dernières fiches avant chaque mois
- Composants préservés depuis les fiches précédentes

### Exemple 3 : Période Partielle

**Configuration :**
- Période : `10/02/2024` → `25/02/2024`
- Salaire de base : *(vide)*
- Écraser : ✅
- Moyenne : ❌

**Résultat :**
- 1 fiche créée pour Février (10/02 → 25/02)
- Salaire de base = dernier salaire connu avant février
- Dates exactes respectées

---

## 🔧 Logique de Détermination du Salaire

### Priorité 1 : Salaire Manuel
Si un montant est spécifié dans le formulaire :
```php
✅ Utilise ce montant pour tous les mois
✅ Copie les composants (primes, déductions) du dernier salaire
```

### Priorité 2 : Option "Moyenne"
Si l'option "Utiliser la moyenne" est cochée :
```php
✅ Calcule la moyenne des 3 dernières fiches avant chaque mois
✅ Exemple pour Mars 2024 : moyenne des fiches de Déc 2023, Jan 2024, Fév 2024
```

### Priorité 3 : Dernier Salaire Connu
Par défaut :
```php
✅ Utilise le salaire de base de la dernière fiche avant chaque mois
✅ Copie tous les composants (primes, déductions)
```

### Priorité 4 : Structure Salariale
Si aucun historique n'existe :
```php
✅ Utilise le montant de base de la structure salariale assignée
✅ Ou le champ "salary_rate" de l'employé
✅ Ou un salaire minimum par défaut (1500 €)
```

---

## 📊 Messages de Retour

### Messages de Succès
```
✅ 12 fiche(s) de paie créée(s) avec succès.
ℹ️ 3 fiche(s) de paie ignorée(s) (déjà existante(s)).
🗑️ 2 fiche(s) de paie supprimée(s) avant recréation.
```

### Messages d'Erreur
```
❌ Failed to create salary slip for employee EMP-001: Invalid base amount
❌ Aucune structure salariale disponible pour l'employé EMP-002
❌ Impossible d'assigner une structure salariale à l'employé EMP-003
```

### Messages d'Information
```
⚠️ Aucune fiche de paie n'a été générée. Vérifiez qu'il y a des employés actifs.
```

---

## 🔍 Logs Détaillés

Les logs sont disponibles dans `var/log/dev.log` :

```log
[INFO] Starting salary slip generation: period=2024-01-15 to 2024-03-10, overwrite=yes
[INFO] Period split into monthly periods: total_periods=3
[INFO] Processing employee EMP-001 for all monthly periods: periods_count=3
[INFO] Processing monthly period for employee EMP-001: period_index=1, period=2024-01-15 to 2024-01-31
[INFO] Generating salary for specific period: employee=EMP-001, period=2024-01-15 to 2024-01-31
[INFO] Using last known base salary: employee=EMP-001, base_salary=2800
[INFO] Creating salary slip: employee=EMP-001, structure=Standard Salary Structure
[INFO] Salary slip created successfully: employee=EMP-001, slip=SAL-SLIP-2024-00001
```

---

## 🛠️ Dépannage

### Problème : Aucune fiche générée

**Causes possibles :**
- Aucun employé actif
- Aucune structure salariale configurée
- Permissions ERPNext insuffisantes

**Solutions :**
1. Vérifier les employés actifs dans ERPNext
2. Configurer au moins une structure salariale
3. Vérifier les permissions API ERPNext

### Problème : Erreurs de montant invalide

**Causes possibles :**
- Aucun historique de salaire
- Structure salariale sans montant de base
- Champ "salary_rate" vide

**Solutions :**
1. Spécifier un salaire de base manuel
2. Configurer les montants dans la structure salariale
3. Remplir le champ "salary_rate" des employés

### Problème : Fiches non visibles dans ERPNext

**Causes possibles :**
- Fiches créées en statut "Draft"
- Problème de synchronisation

**Solutions :**
1. Vérifier le statut des fiches dans ERPNext
2. Attendre quelques minutes pour la synchronisation
3. Consulter les logs pour les erreurs

---

## 🎯 Bonnes Pratiques

### 1. Planification
- **Testez d'abord** sur une période courte (1-2 mois)
- **Vérifiez les structures salariales** avant la génération massive
- **Sauvegardez** les données ERPNext avant les opérations importantes

### 2. Gestion des Erreurs
- **Consultez les logs** en cas de problème
- **Utilisez l'option "Écraser"** pour corriger les erreurs
- **Vérifiez les permissions** ERPNext régulièrement

### 3. Performance
- **Évitez les périodes trop longues** (> 12 mois) pour de nombreux employés
- **Générez par lots** si vous avez plus de 100 employés
- **Surveillez les logs** pour détecter les problèmes de performance

---

## 📈 Cas d'Usage Typiques

### 1. Rattrapage de Salaires
```
Situation : Salaires de Janvier à Mars 2024 non générés
Solution : Période 01/01/2024 → 31/03/2024, Écraser=Non
Résultat : 3 mois de salaires générés automatiquement
```

### 2. Correction de Montants
```
Situation : Salaires incorrects pour Q1 2024
Solution : Période 01/01/2024 → 31/03/2024, Salaire=3200€, Écraser=Oui
Résultat : Tous les salaires Q1 corrigés avec le bon montant
```

### 3. Génération Prévisionnelle
```
Situation : Préparer les salaires pour les 6 prochains mois
Solution : Période future, Moyenne=Oui, Écraser=Non
Résultat : Fiches préparées avec des montants basés sur l'historique
```

---

## 🔗 Liens Utiles

- **Interface de génération** : `/salary/generator`
- **Statistiques** : `/statistics`
- **Logs Symfony** : `var/log/dev.log`
- **Documentation ERPNext** : [Salary Slip API](https://frappeframework.com/docs)

---

*Cette fonctionnalité a été développée pour simplifier la gestion des salaires sur plusieurs mois tout en respectant les contraintes d'ERPNext.*