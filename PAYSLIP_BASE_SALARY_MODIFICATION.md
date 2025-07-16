# Modification du Salaire de Base dans les Fiches de Paie

## 📋 Résumé de la Fonctionnalité

Cette fonctionnalité permet aux utilisateurs de modifier directement le salaire de base d'une fiche de paie depuis la page de détails. La modification déclenche automatiquement le recalcul de tous les composants de salaire associés.

## 🎯 Fonctionnalités Implémentées

### ✅ Interface Utilisateur
- **Bouton d'édition** dans la section "Gains" de la fiche de paie
- **Modal Bootstrap** pour la saisie du nouveau montant
- **Validation en temps réel** côté client
- **Messages de confirmation** et gestion des erreurs
- **Rechargement automatique** après modification

### ✅ Backend API
- **Nouvelle route** : `POST /payslip/{id}/update-base-salary`
- **Validation robuste** des données JSON
- **Intégration ERPNext** via le service existant
- **Logging détaillé** pour le débogage
- **Gestion d'erreurs** complète

### ✅ Calculs Automatiques
- **Salaire de base** : montant saisi par l'utilisateur
- **Indemnité** : 30% du salaire de base
- **Salaire brut** : salaire de base + indemnité
- **Salaire net** : salaire brut - déductions

## 🔧 Modifications Techniques

### 1. Contrôleur (`src/Controller/PayslipController.php`)

```php
#[Route('/payslip/{id}/update-base-salary', name: 'app_payslip_update_base_salary', methods: ['POST'])]
public function updateBaseSalary(string $id, Request $request): JsonResponse
```

**Fonctionnalités :**
- Décodage de l'ID de la fiche de paie
- Validation des données JSON reçues
- Vérification que le montant est positif
- Appel au service ERPNext pour la mise à jour
- Retour d'une réponse JSON avec le statut

### 2. Template (`templates/payslip/view.html.twig`)

**Ajouts :**
- Bouton d'édition dans l'en-tête de la section "Gains"
- Modal Bootstrap pour la saisie du nouveau montant
- JavaScript pour les interactions AJAX
- Gestion des états de chargement et des erreurs

### 3. Service ERPNext (`src/Service/ErpNextService.php`)

**Utilisation de la méthode existante :**
```php
public function updateSalarySlipAmounts(string $salarySlipName, float $baseAmount): array
```

Cette méthode gère automatiquement :
- La récupération de la fiche de paie complète
- La mise à jour des composants earnings
- Le recalcul des totaux
- La sauvegarde en mode draft

## 📱 Guide d'Utilisation

### Pour l'Utilisateur Final

1. **Accéder à la fiche de paie**
   - Naviguer vers `/payslip/{id}`
   - La page affiche tous les détails de la fiche de paie

2. **Modifier le salaire de base**
   - Cliquer sur le bouton d'édition (icône crayon) dans la section "Gains"
   - Une modal s'ouvre avec le montant actuel
   - Saisir le nouveau montant du salaire de base
   - Cliquer sur "Enregistrer"

3. **Confirmation**
   - Un message de succès s'affiche
   - La page se recharge automatiquement
   - Les nouveaux montants sont visibles immédiatement

### Pour le Développeur

1. **Structure de la requête AJAX**
```javascript
fetch('/payslip/{id}/update-base-salary', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    },
    body: JSON.stringify({
        base_salary: newBaseSalary
    })
})
```

2. **Format de la réponse**
```json
{
    "success": true,
    "message": "Salaire de base mis à jour avec succès",
    "data": { /* données de la fiche mise à jour */ }
}
```

## 🔒 Sécurité et Validation

### Validation Côté Client (JavaScript)
- Vérification que le montant est un nombre
- Validation que le montant est positif
- Validation HTML5 du formulaire

### Validation Côté Serveur (PHP)
- Vérification de la structure JSON
- Validation du type de données (numeric)
- Contrôle que le montant est strictement positif
- Gestion des exceptions avec messages explicites

### Gestion des Erreurs
- Messages d'erreur explicites pour l'utilisateur
- Logging détaillé pour le débogage
- Codes de statut HTTP appropriés
- Rollback automatique en cas d'échec

## 📊 Exemple de Calcul

```
Salaire de base saisi : 3 000,00 €
Indemnité (30%)       :   900,00 €
                        -----------
Salaire brut          : 3 900,00 €
Déductions            :   850,50 €
                        -----------
Salaire net           : 3 049,50 €
```

## 🚀 Intégration ERPNext

La fonctionnalité s'intègre parfaitement avec ERPNext :

- **Récupération** : Utilise l'API ERPNext pour récupérer la fiche complète
- **Mise à jour** : Modifie les composants earnings selon les règles métier
- **Sauvegarde** : Enregistre en mode draft pour permettre les modifications
- **Cohérence** : Maintient la cohérence des données avec ERPNext

## 🧪 Tests

Des scripts de test ont été créés pour valider :
- La validation des données JSON
- La logique de calcul des montants
- L'encodage/décodage des IDs
- La structure HTML du template
- L'intégration avec l'API

## 📝 Fichiers Modifiés

1. **`src/Controller/PayslipController.php`**
   - Ajout de la méthode `updateBaseSalary()`
   - Import des classes nécessaires

2. **`templates/payslip/view.html.twig`**
   - Ajout du bouton d'édition
   - Création de la modal de modification
   - Intégration du JavaScript AJAX

## 🎉 Résultat Final

La fonctionnalité est maintenant opérationnelle et permet :
- ✅ Modification intuitive du salaire de base
- ✅ Recalcul automatique des composants
- ✅ Interface utilisateur moderne et responsive
- ✅ Intégration transparente avec ERPNext
- ✅ Gestion robuste des erreurs
- ✅ Validation complète des données

La modification du salaire de base est désormais disponible sur toutes les fiches de paie avec une interface utilisateur professionnelle et une logique métier robuste.