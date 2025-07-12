# 🔧 Guide de Résolution des Problèmes - Générateur de Salaire

## 🚨 Problèmes Courants et Solutions

### 1. Erreur: "Failed to delete salary slip"

#### 🔍 Symptômes
```
❌ Employee HR-EMP-00029: Failed to delete salary slip: Sal Slip/HR-EMP-00029/00005
```

#### 🎯 Causes Possibles
- **Permissions insuffisantes** dans ERPNext
- **Fiche de paie verrouillée** ou en cours d'utilisation
- **Statut de document** ne permettant pas la suppression
- **Contraintes de base de données** ERPNext

#### ✅ Solutions

**Solution 1: Vérifier les permissions**
1. Connectez-vous à ERPNext en tant qu'administrateur
2. Allez dans `Paramètres > Utilisateurs et permissions > Rôles et permissions`
3. Vérifiez que l'utilisateur API a les droits :
   - `Delete` sur le doctype `Salary Slip`
   - `Cancel` sur le doctype `Salary Slip`
   - `Write` sur le doctype `Salary Slip`

**Solution 2: Supprimer manuellement dans ERPNext**
1. Allez dans ERPNext > Paie > Fiche de paie
2. Trouvez les fiches problématiques
3. Annulez-les manuellement (bouton "Annuler")
4. Supprimez-les manuellement (bouton "Supprimer")
5. Relancez la génération

**Solution 3: Utiliser le script de diagnostic**
```bash
php debug_salary_issues.php
```

### 2. Erreur: "Aucun salaire de base trouvé"

#### 🔍 Symptômes
```
❌ Aucun salaire de base trouvé pour l'employé HR-EMP-00029
```

#### 🎯 Causes Possibles
- **Aucune assignation de structure salariale**
- **Structure salariale sans montant de base**
- **Aucune fiche de paie précédente**
- **Données employé incomplètes**

#### ✅ Solutions

**Solution 1: Vérifier l'assignation de structure salariale**
1. Dans ERPNext, allez à `Paie > Assignation de structure salariale`
2. Vérifiez qu'il existe une assignation pour l'employé
3. Si aucune assignation :
   - Créez une nouvelle assignation
   - Définissez un montant de base
   - Activez l'assignation

**Solution 2: Créer/Modifier la structure salariale**
1. Allez à `Paie > Structure salariale`
2. Vérifiez que la structure a des composants définis
3. Assurez-vous qu'il y a un composant "Salaire de base" ou similaire

**Solution 3: Définir un salaire dans les détails de l'employé**
1. Allez à `RH > Employé`
2. Ouvrez la fiche de l'employé problématique
3. Ajoutez un champ `salary_rate` ou équivalent

**Solution 4: Utiliser un salaire spécifique**
1. Dans le générateur, saisissez un montant dans "Salaire de base"
2. Cochez "Écraser les valeurs existantes"
3. Relancez la génération

### 3. Erreur: "Salary Slip already created for this period"

#### 🔍 Symptômes
```
❌ ValidationError: Salary Slip of employee HR-EMP-00029 already created for this period
```

#### 🎯 Cause
- Option "Écraser les valeurs existantes" non cochée
- Échec de suppression des fiches existantes

#### ✅ Solutions

**Solution 1: Activer l'écrasement**
1. Cochez "Écraser les valeurs existantes"
2. Relancez la génération

**Solution 2: Supprimer manuellement les fiches**
1. Supprimez les fiches existantes dans ERPNext
2. Relancez la génération sans écrasement

## 🛠️ Outils de Diagnostic

### Script de Diagnostic Complet
```bash
php debug_salary_issues.php
```

Ce script vérifie :
- ✅ Détails des employés problématiques
- ✅ Assignations de structures salariales
- ✅ Fiches de paie existantes
- ✅ Capacité de suppression
- ✅ Structures salariales disponibles

### Logs Détaillés
Consultez les logs Symfony dans `var/log/` pour plus de détails :
```bash
tail -f var/log/dev.log | grep -i salary
```

## 🔧 Actions Préventives

### 1. Configuration ERPNext Recommandée

**Structures Salariales :**
- Créez au moins une structure salariale par défaut
- Définissez des composants de base (Salaire de base, HRA, etc.)
- Activez les structures

**Assignations :**
- Assignez une structure à chaque employé actif
- Définissez des montants de base réalistes
- Vérifiez les dates de validité

**Permissions :**
- Accordez les droits complets sur `Salary Slip` à l'utilisateur API
- Testez les permissions avec l'utilisateur API

### 2. Bonnes Pratiques d'Utilisation

**Avant la génération :**
1. Vérifiez qu'il n'y a pas de fiches en cours de traitement
2. Testez sur une petite période d'abord
3. Sauvegardez vos données importantes

**Pendant la génération :**
1. Ne fermez pas la page pendant le traitement
2. Surveillez les messages d'erreur
3. Consultez les logs en cas de problème

**Après la génération :**
1. Vérifiez les résultats dans ERPNext
2. Contrôlez les montants générés
3. Validez que tous les employés sont traités

## 🆘 Support Avancé

### Si les problèmes persistent :

1. **Collectez les informations :**
   - Messages d'erreur complets
   - Logs Symfony (`var/log/dev.log`)
   - Résultat du script de diagnostic
   - Configuration ERPNext (structures, assignations)

2. **Vérifiez l'environnement :**
   - Version ERPNext
   - Permissions utilisateur API
   - Connectivité réseau
   - Espace disque disponible

3. **Solutions de contournement :**
   - Génération par petits groupes d'employés
   - Création manuelle des fiches problématiques
   - Utilisation d'un salaire de base fixe

### Commandes Utiles

**Vérifier la connectivité ERPNext :**
```bash
curl -X GET "http://your-erpnext-url/api/resource/Employee" \
  -H "Authorization: token api_key:api_secret"
```

**Tester les permissions :**
```bash
curl -X DELETE "http://your-erpnext-url/api/resource/Salary Slip/TEST-SLIP" \
  -H "Authorization: token api_key:api_secret"
```

**Vérifier les logs ERPNext :**
```bash
# Sur le serveur ERPNext
tail -f sites/site_name/logs/web.log
```

---

## 📞 Escalade

Si aucune solution ne fonctionne :
1. Documentez le problème avec tous les détails
2. Incluez les logs complets
3. Précisez la configuration ERPNext
4. Contactez le support technique

**🎯 La plupart des problèmes sont liés aux permissions ERPNext ou aux données manquantes. Vérifiez toujours ces aspects en premier !**