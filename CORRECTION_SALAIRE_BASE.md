# Correction du problème de mise à jour du salaire de base

## Problème identifié

L'erreur "Une erreur est survenue lors de la mise à jour du salaire de base" était causée par un problème de routage dans Symfony.

### Cause racine

Les routes étaient déclarées dans le mauvais ordre dans `PayslipController.php` :

1. **Route générale** : `/payslip/{id}` (ligne 25) - capturait TOUTES les URLs commençant par `/payslip/`
2. **Route spécifique** : `/payslip/{id}/update-base-salary` (ligne 147) - n'était jamais atteinte

Quand une requête était faite vers `/payslip/U2FsIFNsaXAvSFItRU1QLTAwMDUwLzAwMDAx/update-base-salary`, Symfony utilisait la première route qui correspondait (`/payslip/{id}`), et l'ID devenait `U2FsIFNsaXAvSFItRU1QLTAwMDUwLzAwMDAx/update-base-salary` au lieu de `U2FsIFNsaXAvSFItRU1QLTAwMDUwLzAwMDAx`.

## Corrections apportées

### 1. Réorganisation des routes

**Avant :**
```php
#[Route('/payslip/{id}', name: 'app_payslip_view', requirements: ['id' => '.+'])]
public function view(string $id): Response

// ... autres méthodes ...

#[Route('/payslip/{id}/update-base-salary', name: 'app_payslip_update_base_salary', methods: ['POST'], requirements: ['id' => '.+'])]
public function updateBaseSalary(string $id, Request $request): JsonResponse
```

**Après :**
```php
#[Route('/payslip/{id}/update-base-salary', name: 'app_payslip_update_base_salary', methods: ['POST'], requirements: ['id' => '.+'])]
public function updateBaseSalary(string $id, Request $request): JsonResponse

#[Route('/payslip/{id}', name: 'app_payslip_view', requirements: ['id' => '.+'])]
public function view(string $id): Response
```

### 2. Suppression de la duplication

- Supprimé la méthode `updateBaseSalary` dupliquée qui se trouvait à la fin du fichier
- Gardé uniquement la version au début du contrôleur

### 3. Amélioration du logging

**Dans ErpNextService.php :**
- Ajout de logs détaillés au début de `updateSalarySlipAmounts()`
- Ajout de logs avant et après la sauvegarde
- Logs d'erreur plus détaillés

**Dans le template JavaScript :**
- Ajout de logs de la requête avant envoi
- Logs d'erreur plus détaillés avec contexte
- Affichage des informations de débogage dans la console

### 4. Vérification des routes

```bash
php bin/console debug:router | grep payslip
```

**Résultat (ordre correct) :**
```
app_payslip_update_base_salary    POST       /payslip/{id}/update-base-salary
app_payslip_view                  ANY        /payslip/{id}
app_payslip_pdf                   ANY        /payslip/{id}/pdf
```

## Test de la correction

1. **Cache vidé :** `php bin/console cache:clear`
2. **Routes vérifiées :** Ordre correct confirmé
3. **Script de test créé :** `test_salary_update.php`

## Comment tester

1. Accédez à une fiche de paie : `/payslip/{id}`
2. Cliquez sur le bouton d'édition du salaire de base
3. Modifiez le montant
4. Cliquez sur "Enregistrer"
5. Vérifiez la console du navigateur (F12) pour les logs détaillés

## Logs à surveiller

**Dans les logs Symfony (`var/log/dev.log`) :**
- `Starting salary slip update`
- `Retrieved salary slip`
- `Saving updated salary slip`
- `Salary slip saved successfully`

**Dans la console du navigateur :**
- `Envoi de la requête de mise à jour`
- Détails de l'erreur si elle persiste

## Prochaines étapes

Si le problème persiste après cette correction :

1. Vérifiez les logs Symfony pour identifier l'erreur exacte
2. Vérifiez la console du navigateur pour les détails de la requête
3. Vérifiez que ERPNext est accessible et répond correctement
4. Testez avec des données de test si ERPNext n'est pas disponible

La correction principale (réorganisation des routes) devrait résoudre l'erreur immédiate de routage.