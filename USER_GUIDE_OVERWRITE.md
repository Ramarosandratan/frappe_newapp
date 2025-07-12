# 👥 Guide Utilisateur - Option "Écraser si existant"

## 🎯 À quoi sert cette option ?

L'option **"Écraser les valeurs existantes"** vous permet de **remplacer** des fiches de paie déjà créées pour une période donnée.

## 🔄 Comportements selon l'option

### ❌ Option NON cochée (comportement par défaut)
- Le système **ignore** les employés qui ont déjà une fiche de paie pour la période
- Message affiché : `ℹ️ X fiche(s) de paie ignorée(s) (déjà existante(s))`
- **Aucune modification** des fiches existantes

### ✅ Option COCHÉE (écrasement activé)
- Le système **supprime** automatiquement les fiches existantes
- Puis **recrée** de nouvelles fiches avec vos nouveaux paramètres
- Messages affichés :
  - `🗑️ X fiche(s) de paie supprimée(s) avant recréation`
  - `✅ X fiche(s) de paie créée(s) avec succès`

## 📋 Cas d'usage typiques

### 1. Correction d'erreurs
**Situation :** Vous avez créé des fiches avec un mauvais salaire de base
**Solution :** 
1. Cochez "Écraser les valeurs existantes"
2. Saisissez le bon salaire de base
3. Relancez la génération

### 2. Mise à jour des salaires
**Situation :** Les salaires ont été augmentés après la génération
**Solution :**
1. Cochez "Écraser les valeurs existantes"
2. Cochez "Utiliser la moyenne" ou saisissez le nouveau salaire
3. Relancez la génération

### 3. Changement de méthode de calcul
**Situation :** Vous voulez passer du dernier salaire à la moyenne
**Solution :**
1. Cochez "Écraser les valeurs existantes"
2. Cochez "Utiliser la moyenne des salaires de base"
3. Relancez la génération

## ⚠️ Points d'attention

### 🔒 Action irréversible
- Une fois les fiches supprimées, **impossible de les récupérer**
- Assurez-vous de vos paramètres avant de lancer

### 📊 Impact sur ERPNext
- Les fiches sont **définitivement supprimées** d'ERPNext
- Les rapports basés sur ces fiches seront mis à jour
- L'historique des modifications est conservé dans les logs

### 🕐 Temps de traitement
- La suppression puis recréation prend plus de temps
- Soyez patient, surtout avec beaucoup d'employés

## 🎯 Exemple pratique

### Scénario
Vous avez généré les fiches de janvier avec un salaire de base de 2500€, mais vous réalisez qu'il fallait 2800€.

### Étapes
1. Allez sur `/salary/generator`
2. Sélectionnez la période : 01/01/2024 à 31/01/2024
3. Saisissez 2800 dans "Salaire de base"
4. **Cochez "Écraser les valeurs existantes"**
5. Cliquez sur "Générer les fiches de paie"

### Résultat attendu
```
🗑️ 15 fiche(s) de paie supprimée(s) avant recréation.
✅ 15 fiche(s) de paie créée(s) avec succès.
```

## 🚨 Messages d'erreur possibles

### Erreur de suppression
```
❌ Employee HR-EMP-001: Failed to delete salary slip: SAL-2024-001
```
**Cause :** Problème de permissions ou fiche verrouillée dans ERPNext
**Solution :** Vérifiez les permissions dans ERPNext

### Erreur de recréation
```
❌ Failed to create salary slip for employee HR-EMP-001: [détail de l'erreur]
```
**Cause :** Problème de données ou de configuration
**Solution :** Vérifiez la structure salariale et les données employé

## 💡 Conseils d'utilisation

### ✅ Bonnes pratiques
- **Testez d'abord** sur une petite période ou un employé
- **Vérifiez les résultats** dans ERPNext après génération
- **Sauvegardez** vos données importantes avant écrasement
- **Utilisez les logs** pour diagnostiquer les problèmes

### ❌ À éviter
- Ne pas écraser pendant les heures de pointe
- Ne pas écraser sans vérifier les nouveaux paramètres
- Ne pas ignorer les messages d'erreur

## 🔍 Vérification des résultats

Après génération avec écrasement :
1. Allez dans ERPNext → Paie → Fiche de paie
2. Filtrez par période et vérifiez les montants
3. Contrôlez que les dates de création sont récentes
4. Vérifiez que tous les employés sont présents

---

**💪 Avec cette fonctionnalité, vous avez un contrôle total sur vos fiches de paie !**