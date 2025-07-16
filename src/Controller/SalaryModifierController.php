<?php

namespace App\Controller;

use App\Service\ErpNextService;
use App\Service\MonthlyPercentageService;
use App\Service\ChangeHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

/**
 * Contrôleur pour la modification des fiches de paie
 * 
 * Ce contrôleur gère deux modes de modification :
 * 1. Modification classique : avec condition et nouvelle valeur fixe
 * 2. Pourcentages mensuels : application de pourcentages différents selon le mois
 * 
 * Fonctionnalités principales :
 * - Modification des composants de salaire (gains et déductions)
 * - Gestion des fiches annulées (conversion en draft)
 * - Recalcul automatique des totaux et composants dépendants
 * - Validation des données avant sauvegarde
 * - Logs détaillés pour le débogage
 */
class SalaryModifierController extends AbstractController
{
    /** @var ErpNextService Service pour communiquer avec ERPNext */
    private $erpNextService;
    
    /** @var MonthlyPercentageService Service pour gérer les pourcentages mensuels */
    private $monthlyPercentageService;
    
    /** @var LoggerInterface Logger pour tracer les opérations */
    private $logger;
    
    /** @var ChangeHistoryService Service pour l'historique des modifications */
    private $changeHistoryService;

    /**
     * Constructeur - Injection des dépendances
     * 
     * @param ErpNextService $erpNextService Service ERPNext
     * @param MonthlyPercentageService $monthlyPercentageService Service des pourcentages
     * @param LoggerInterface $logger Logger Symfony
     * @param ChangeHistoryService $changeHistoryService Service pour l'historique
     */
    public function __construct(
        ErpNextService $erpNextService, 
        MonthlyPercentageService $monthlyPercentageService,
        LoggerInterface $logger,
        ChangeHistoryService $changeHistoryService
    ) {
        $this->erpNextService = $erpNextService;
        $this->monthlyPercentageService = $monthlyPercentageService;
        $this->logger = $logger;
        $this->changeHistoryService = $changeHistoryService;
    }

    /**
     * Page principale de modification des fiches de paie
     * 
     * Gère l'affichage du formulaire et le traitement des modifications.
     * Supporte deux modes :
     * - Mode classique : condition + nouvelle valeur
     * - Mode pourcentages mensuels : pourcentages différents par mois
     * 
     * @param Request $request Requête HTTP
     * @return Response Réponse HTTP
     */
    #[Route('/salary/modifier', name: 'app_salary_modifier')]
    public function index(Request $request): Response
    {
        // Récupérer tous les composants de salaire disponibles depuis ERPNext
        $salaryComponents = $this->erpNextService->getSalaryComponents();
        
        // Définir les conditions de comparaison disponibles pour le mode classique
        $conditions = [
            '=' => 'Égal à',
            '>' => 'Supérieur à',
            '<' => 'Inférieur à',
            '>=' => 'Supérieur ou égal à',
            '<=' => 'Inférieur ou égal à',
            '!=' => 'Différent de'
        ];
        
        // Traitement du formulaire soumis
        if ($request->isMethod('POST')) {
            try {
                // === RÉCUPÉRATION DES PARAMÈTRES DU FORMULAIRE ===
                $component = $request->request->get('component');                    // Composant à modifier
                $condition = $request->request->get('condition');                    // Condition (=, >, <, etc.)
                $conditionValue = $request->request->get('condition_value');         // Valeur de la condition
                $newValue = $request->request->get('new_value');                     // Nouvelle valeur (mode classique)
                $startDate = $request->request->get('start_date');                   // Date de début de période
                $endDate = $request->request->get('end_date');                       // Date de fin de période
                $useMonthlyPercentages = $request->request->get('use_monthly_percentages') === '1'; // Mode pourcentages mensuels
                $monthlyPercentages = $request->request->all('monthly_percentages') ?: [];          // Pourcentages par mois
                
                // Log des paramètres reçus pour débogage
                $this->logger->info("Form parameters received", [
                    'component' => $component,
                    'condition' => $condition,
                    'condition_value_raw' => $conditionValue,
                    'new_value_raw' => $newValue,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'use_monthly_percentages' => $useMonthlyPercentages,
                    'monthly_percentages' => $monthlyPercentages
                ]);
                
                // === VALIDATION DES ENTRÉES ===
                if (!$component) {
                    throw new \InvalidArgumentException("Le composant est requis");
                }
                
                if ($useMonthlyPercentages) {
                    // MODE POURCENTAGES MENSUELS : Pas besoin de condition ni de nouvelle valeur
                    // Les pourcentages sont appliqués selon le mois de chaque fiche de paie
                    $this->logger->info("Using monthly percentages mode", [
                        'component' => $component,
                        'percentages_count' => count($monthlyPercentages)
                    ]);
                } else {
                    // MODE CLASSIQUE : Condition et nouvelle valeur sont requises
                    if (!$condition || $conditionValue === null || $newValue === null) {
                        throw new \InvalidArgumentException("Condition, valeur de condition et nouvelle valeur sont requis pour la modification classique");
                    }
                }
                
                // === SAUVEGARDE DES POURCENTAGES MENSUELS ===
                if ($useMonthlyPercentages) {
                    // Récupérer les anciens pourcentages pour l'historique
                    $oldPercentages = $this->monthlyPercentageService->getMonthlyPercentages($component);
                    
                    // Sauvegarder les pourcentages en base de données pour réutilisation future
                    $this->monthlyPercentageService->saveMonthlyPercentages($component, $monthlyPercentages);
                    
                    // Enregistrer l'historique des modifications de pourcentages
                    for ($month = 1; $month <= 12; $month++) {
                        $newPercentage = null;
                        if (isset($monthlyPercentages[$month]) && $monthlyPercentages[$month] !== '') {
                            $newPercentage = (float) $monthlyPercentages[$month];
                        }
                        
                        $oldPercentage = $oldPercentages[$month] ?? null;
                        if ($oldPercentage !== $newPercentage) {
                            $this->changeHistoryService->logMonthlyPercentageChange(
                                $component,
                                $month,
                                $oldPercentage,
                                $newPercentage,
                                "Modification des pourcentages mensuels via l'interface web"
                            );
                        }
                    }
                }
                
                // === CONVERSION DES TYPES ===
                // Convertir les valeurs en nombres pour les calculs (si applicables)
                $conditionValue = is_numeric($conditionValue) ? (float)$conditionValue : $conditionValue;
                $newValue = is_numeric($newValue) ? (float)$newValue : $newValue;
                
                // Log des paramètres après conversion pour vérification
                $this->logger->info("Form parameters after conversion", [
                    'component' => $component,
                    'condition' => $condition,
                    'condition_value_converted' => $conditionValue,
                    'new_value_converted' => $newValue,
                    'condition_value_type' => gettype($conditionValue),
                    'new_value_type' => gettype($newValue)
                ]);
                
                // === RÉCUPÉRATION DES FICHES DE PAIE ===
                // Convertir les dates en objets DateTime pour le formatage
                $startDateTime = new \DateTime($startDate);
                $endDateTime = new \DateTime($endDate);
                
                // Récupérer toutes les fiches de paie pour la période spécifiée depuis ERPNext
                $salarySlipsList = $this->erpNextService->getSalarySlipsByPeriod(
                    $startDateTime->format('Y-m-d'),
                    $endDateTime->format('Y-m-d')
                );
                
                $this->logger->info("Found salary slips for modification", [
                    'count' => count($salarySlipsList),
                    'period' => $startDate . ' to ' . $endDate
                ]);
                
                // Vérifier si des fiches de paie ont été trouvées
                if (empty($salarySlipsList)) {
                    $this->addFlash('warning', sprintf(
                        'Aucune fiche de paie trouvée pour la période du %s au %s.',
                        $startDate,
                        $endDate
                    ));
                    return $this->redirectToRoute('app_salary_modifier');
                }
                
                $modifiedCount = 0;
                $skippedCount = 0;
                $errorCount = 0;
                
                foreach ($salarySlipsList as $slipSummary) {
                    try {
                        $this->logger->info("Processing salary slip", [
                            'slip_name' => $slipSummary['name'] ?? 'unknown',
                            'slip_summary' => $slipSummary
                        ]);
                        
                        // Récupérer les détails complets de la fiche de paie
                        $slip = $this->erpNextService->getSalarySlipDetails($slipSummary['name']);
                        
                        if (!$slip) {
                            $this->logger->warning("Could not retrieve details for salary slip", [
                                'slip_name' => $slipSummary['name'],
                                'slip_summary' => $slipSummary
                            ]);
                            $errorCount++;
                            continue;
                        }
                        
                        // Vérifier le statut de la fiche de paie
                        $docstatus = $slip['docstatus'] ?? 0;
                        $this->logger->info("Processing salary slip with status", [
                            'slip_name' => $slip['name'],
                            'docstatus' => $docstatus,
                            'status_meaning' => $docstatus == 0 ? 'Draft' : ($docstatus == 1 ? 'Submitted' : 'Cancelled')
                        ]);
                        
                        $this->logger->info("Retrieved salary slip details", [
                            'slip_name' => $slip['name'] ?? 'unknown',
                            'docstatus' => $docstatus,
                            'has_earnings' => isset($slip['earnings']),
                            'has_deductions' => isset($slip['deductions']),
                            'earnings_count' => isset($slip['earnings']) ? count($slip['earnings']) : 0,
                            'deductions_count' => isset($slip['deductions']) ? count($slip['deductions']) : 0,
                            'start_date' => $slip['start_date'] ?? 'not_set'
                        ]);
                        
                        $modified = false;
                        $componentFound = false;
                        
                        // Extraire le mois de la fiche de paie pour les pourcentages mensuels
                        $slipMonth = null;
                        if (isset($slip['start_date'])) {
                            $slipDate = new \DateTime($slip['start_date']);
                            $slipMonth = (int) $slipDate->format('n'); // n = mois sans zéro initial
                        }
                        
                        // Log des composants disponibles pour débogage
                        $availableEarnings = [];
                        $availableDeductions = [];
                        
                        if (isset($slip['earnings'])) {
                            foreach ($slip['earnings'] as $earning) {
                                $availableEarnings[] = $earning['salary_component'] . ' (' . $earning['amount'] . ')';
                            }
                        }
                        
                        if (isset($slip['deductions'])) {
                            foreach ($slip['deductions'] as $deduction) {
                                $availableDeductions[] = $deduction['salary_component'] . ' (' . $deduction['amount'] . ')';
                            }
                        }
                        

                        
                        // Vérifier si le composant existe dans les gains
                        if (isset($slip['earnings'])) {
                            foreach ($slip['earnings'] as $index => $earning) {
                                // Comparaison flexible pour les noms de composants
                                $earningComponent = strtolower(trim($earning['salary_component']));
                                $searchComponent = strtolower(trim($component));
                                
                                // Normaliser les variations communes
                                $earningComponent = str_replace(['salaire de base', 'salaire base'], 'salaire_base', $earningComponent);
                                $searchComponent = str_replace(['salaire de base', 'salaire base'], 'salaire_base', $searchComponent);
                                
                                if ($earningComponent === $searchComponent || $earning['salary_component'] === $component) {
                                    $componentFound = true;
                                    $currentValue = $earning['amount'];
                                    
                                    // Pour les pourcentages mensuels, pas de vérification de condition
                                    $conditionMet = $useMonthlyPercentages || $this->checkCondition($currentValue, $condition, $conditionValue);
                                    
                                    if ($conditionMet) {
                                        if ($useMonthlyPercentages && $slipMonth !== null) {
                                            // SOLUTION SIMPLE: Prendre la valeur, multiplier par le pourcentage, remplacer
                                            $finalValue = $this->monthlyPercentageService->applyMonthlyPercentage(
                                                $currentValue, 
                                                $slipMonth, 
                                                $component
                                            );
                                        } else {
                                            // Mode classique
                                            $finalValue = $newValue;
                                        }
                                        
                                        // Supprimer l'ancienne valeur et entrer la nouvelle
                                        $slip['earnings'][$index]['amount'] = $finalValue;
                                        $modified = true;
                                        
                                        // Enregistrer la modification dans l'historique
                                        $reason = $useMonthlyPercentages 
                                            ? "Modification par pourcentage mensuel (mois {$slipMonth})"
                                            : "Modification par condition ({$condition} {$conditionValue})";
                                        
                                        $this->changeHistoryService->logPayslipChange(
                                            $slip['name'],
                                            $component,
                                            $currentValue,
                                            $finalValue,
                                            $reason
                                        );
                                        
                                        $this->logger->info("Modified earning component", [
                                            'slip' => $slip['name'],
                                            'component' => $component,
                                            'old_value' => $currentValue,
                                            'new_value' => $finalValue,
                                            'month' => $slipMonth,
                                            'used_monthly_percentage' => $useMonthlyPercentages
                                        ]);
                                        
                                        // Recalculer les composants dépendants selon le composant modifié
                                        $this->handleComponentDependencies($slip, $component, $finalValue);
                                    }
                                }
                            }
                        }
                        
                        // Vérifier si le composant existe dans les déductions
                        if (isset($slip['deductions'])) {
                            foreach ($slip['deductions'] as $index => $deduction) {
                                // Comparaison flexible pour les noms de composants
                                $deductionComponent = strtolower(trim($deduction['salary_component']));
                                $searchComponent = strtolower(trim($component));
                                
                                // Normaliser les variations communes
                                $deductionComponent = str_replace(['salaire de base', 'salaire base'], 'salaire_base', $deductionComponent);
                                $searchComponent = str_replace(['salaire de base', 'salaire base'], 'salaire_base', $searchComponent);
                                
                                if ($deductionComponent === $searchComponent || $deduction['salary_component'] === $component) {
                                    $componentFound = true;
                                    $currentValue = $deduction['amount'];
                                    
                                    // Pour les pourcentages mensuels, pas de vérification de condition
                                    $conditionMet = $useMonthlyPercentages || $this->checkCondition($currentValue, $condition, $conditionValue);
                                    
                                    if ($conditionMet) {
                                        if ($useMonthlyPercentages && $slipMonth !== null) {
                                            // SOLUTION SIMPLE: Prendre la valeur, multiplier par le pourcentage, remplacer
                                            $finalValue = $this->monthlyPercentageService->applyMonthlyPercentage(
                                                $currentValue, 
                                                $slipMonth, 
                                                $component
                                            );
                                        } else {
                                            // Mode classique
                                            $finalValue = $newValue;
                                        }
                                        
                                        // Supprimer l'ancienne valeur et entrer la nouvelle
                                        $slip['deductions'][$index]['amount'] = $finalValue;
                                        $modified = true;
                                        
                                        // Enregistrer la modification dans l'historique
                                        $reason = $useMonthlyPercentages 
                                            ? "Modification par pourcentage mensuel (mois {$slipMonth})"
                                            : "Modification par condition ({$condition} {$conditionValue})";
                                        
                                        $this->changeHistoryService->logPayslipChange(
                                            $slip['name'],
                                            $component,
                                            $currentValue,
                                            $finalValue,
                                            $reason
                                        );
                                        
                                        $this->logger->info("Modified deduction component", [
                                            'slip' => $slip['name'],
                                            'component' => $component,
                                            'old_value' => $currentValue,
                                            'new_value' => $finalValue,
                                            'month' => $slipMonth,
                                            'used_monthly_percentage' => $useMonthlyPercentages
                                        ]);
                                        
                                        // Recalculer les composants dépendants selon le composant modifié
                                        $this->handleComponentDependencies($slip, $component, $finalValue);
                                    }
                                }
                            }
                        }
                        

                        
                        // Log si le composant n'a pas été trouvé
                        if (!$componentFound) {
                            $this->logger->warning("Component not found in salary slip", [
                                'slip_name' => $slip['name'],
                                'component_searched' => $component,
                                'available_earnings' => $availableEarnings,
                                'available_deductions' => $availableDeductions
                            ]);
                        }
                        
                        // Si des modifications ont été apportées, mettre à jour la fiche de paie
                        if ($modified) {
                            // Recalculer les totaux finaux après toutes les modifications
                            $this->recalculateSalarySlipTotals($slip);
                            
                            // Validation des totaux avant sauvegarde
                            $this->validateSalarySlipTotals($slip);
                            
                            // Log des totaux avant sauvegarde
                            $this->logger->info("Saving salary slip with updated totals", [
                                'slip' => $slip['name'],
                                'gross_pay_to_save' => $slip['gross_pay'],
                                'total_deduction_to_save' => $slip['total_deduction'],
                                'net_pay_to_save' => $slip['net_pay'],
                                'used_monthly_percentages' => $useMonthlyPercentages,
                                'slip_month' => $slipMonth
                            ]);
                            
                            // Sauvegarder avec gestion d'erreur améliorée
                            try {
                                $this->erpNextService->updateSalarySlip($slip);
                                $modifiedCount++;
                                
                                $this->logger->info("Salary slip successfully updated", [
                                    'slip' => $slip['name'],
                                    'final_gross_pay' => $slip['gross_pay'],
                                    'final_net_pay' => $slip['net_pay']
                                ]);
                            } catch (\Exception $updateException) {
                                $errorMessage = $updateException->getMessage();
                                
                                // Gestion spécifique des erreurs de documents annulés
                                if (strpos($errorMessage, 'Cannot edit cancelled document') !== false || 
                                    strpos($errorMessage, 'Cannot update cancelled salary slip') !== false) {
                                    $this->logger->warning("Skipping cancelled salary slip", [
                                        'slip' => $slip['name'],
                                        'reason' => 'Document is cancelled and cannot be modified',
                                        'docstatus' => $slip['docstatus'] ?? 'unknown',
                                        'error_type' => 'cancelled_document'
                                    ]);
                                    $skippedCount++;
                                } else {
                                    $this->logger->error("Failed to update salary slip with monthly percentages", [
                                        'slip' => $slip['name'],
                                        'error' => $errorMessage,
                                        'error_type' => get_class($updateException),
                                        'slip_data' => [
                                            'gross_pay' => $slip['gross_pay'],
                                            'total_deduction' => $slip['total_deduction'],
                                            'net_pay' => $slip['net_pay'],
                                            'docstatus' => $slip['docstatus'] ?? 'unknown'
                                        ]
                                    ]);
                                    $errorCount++;
                                }
                                continue;
                            }
                        } else {
                            $skippedCount++;
                            $this->logger->info("Salary slip skipped", [
                                'slip_name' => $slip['name'],
                                'reason' => $componentFound ? 'condition_not_met' : 'component_not_found',
                                'component_searched' => $component
                            ]);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error("Failed to modify salary slip", [
                            'slip' => $slipSummary['name'] ?? 'unknown',
                            'error' => $e->getMessage(),
                            'error_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        $errorCount++;
                    }
                }
                
                // Afficher un message de succès
                if ($modifiedCount > 0) {
                    $this->addFlash('success', sprintf(
                        '%d fiches de paie modifiées avec succès sur %d fiches analysées. %d ignorées (condition non respectée). %d erreurs.',
                        $modifiedCount,
                        count($salarySlipsList),
                        $skippedCount,
                        $errorCount
                    ));
                } else {
                    $this->addFlash('warning', sprintf(
                        'Aucune fiche de paie modifiée sur %d fiches analysées. %d ignorées (condition non respectée). %d erreurs.',
                        count($salarySlipsList),
                        $skippedCount,
                        $errorCount
                    ));
                }
                
                return $this->redirectToRoute('app_salary_modifier');
            } catch (\Exception $e) {
                $this->logger->error("Salary modification failed", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                $this->addFlash('error', 'Erreur lors de la modification des fiches de paie: ' . $e->getMessage());
            }
        }
        
        return $this->render('salary_modifier/index.html.twig', [
            'salaryComponents' => $salaryComponents,
            'conditions' => $conditions,
            'monthNames' => $this->monthlyPercentageService->getMonthNames()
        ]);
    }

    #[Route('/salary/modifier/percentages/{component}', name: 'app_salary_modifier_percentages', methods: ['GET'])]
    public function getMonthlyPercentages(string $component): Response
    {
        $percentages = $this->monthlyPercentageService->getMonthlyPercentages($component);
        
        return $this->json([
            'success' => true,
            'percentages' => $percentages
        ]);
    }
    
    /**
     * Vérifie si une valeur respecte une condition
     */
    private function checkCondition($value, $condition, $conditionValue): bool
    {
        // Convertir les valeurs en float pour assurer une comparaison numérique correcte
        $numericValue = is_numeric($value) ? (float)$value : $value;
        $numericConditionValue = is_numeric($conditionValue) ? (float)$conditionValue : $conditionValue;
        

        
        $result = false;
        switch ($condition) {
            case '=':
                $result = $numericValue == $numericConditionValue;
                break;
            case '>':
                $result = $numericValue > $numericConditionValue;
                break;
            case '<':
                $result = $numericValue < $numericConditionValue;
                break;
            case '>=':
                $result = $numericValue >= $numericConditionValue;
                break;
            case '<=':
                $result = $numericValue <= $numericConditionValue;
                break;
            case '!=':
                $result = $numericValue != $numericConditionValue;
                break;
            default:
                $result = false;
        }
        

        
        return $result;
    }
    
    /**
     * Récupère les formules des composants depuis ERPNext
     */
    private function getComponentFormulas(array $slip): array
    {
        $formulas = [];
        
        // Récupérer les formules depuis les earnings
        if (isset($slip['earnings'])) {
            foreach ($slip['earnings'] as $earning) {
                $component = $earning['salary_component'];
                $formula = $earning['formula'] ?? null;
                $isFormulaComponent = ($earning['amount_based_on_formula'] ?? 0) == 1;
                
                if ($isFormulaComponent && $formula) {
                    $formulas[$component] = $formula;
                }
            }
        }
        
        // Récupérer les formules depuis les deductions
        if (isset($slip['deductions'])) {
            foreach ($slip['deductions'] as $deduction) {
                $component = $deduction['salary_component'];
                $formula = $deduction['formula'] ?? null;
                $isFormulaComponent = ($deduction['amount_based_on_formula'] ?? 0) == 1;
                
                if ($isFormulaComponent && $formula) {
                    $formulas[$component] = $formula;
                }
            }
        }
        
        $this->logger->info("Retrieved component formulas", [
            'slip' => $slip['name'],
            'formulas' => $formulas
        ]);
        
        return $formulas;
    }
    
    /**
     * Gère les dépendances entre composants selon le composant modifié
     */
    private function handleComponentDependencies(array &$slip, string $modifiedComponent, float $newValue): void
    {
        $this->logger->info("Handling component dependencies", [
            'slip' => $slip['name'],
            'modified_component' => $modifiedComponent,
            'new_value' => $newValue
        ]);
        
        // Récupérer les formules dynamiquement depuis ERPNext
        $formulas = $this->getComponentFormulas($slip);
        
        // Recalculer tous les composants qui dépendent du composant modifié
        $this->recalculateAllDependentComponents($slip, $modifiedComponent, $newValue, $formulas);
    }
    
    /**
     * Recalcule tous les composants dépendants en utilisant les formules dynamiques
     */
    private function recalculateAllDependentComponents(array &$slip, string $modifiedComponent, float $newValue, array $formulas): void
    {
        $this->logger->info("Recalculating all dependent components with dynamic formulas", [
            'slip' => $slip['name'],
            'modified_component' => $modifiedComponent,
            'new_value' => $newValue,
            'available_formulas' => array_keys($formulas)
        ]);
        
        // Créer un tableau des valeurs actuelles des composants pour l'évaluation des formules
        $componentValues = $this->getComponentValues($slip);
        
        // Mettre à jour la valeur du composant modifié
        $componentAbbr = $this->getComponentAbbreviation($slip, $modifiedComponent);
        if ($componentAbbr) {
            $componentValues[$componentAbbr] = $newValue;
        }
        
        // Recalculer tous les composants qui ont des formules
        foreach ($formulas as $componentName => $formula) {
            // Ne pas recalculer le composant qu'on vient de modifier
            if ($componentName === $modifiedComponent) {
                continue;
            }
            
            try {
                $newAmount = $this->evaluateFormula($formula, $componentValues);
                $this->updateComponentAmount($slip, $componentName, $newAmount);
                
                // Mettre à jour les valeurs pour les prochains calculs
                $abbr = $this->getComponentAbbreviation($slip, $componentName);
                if ($abbr) {
                    $componentValues[$abbr] = $newAmount;
                }
                
                $this->logger->info("Recalculated component using dynamic formula", [
                    'slip' => $slip['name'],
                    'component' => $componentName,
                    'formula' => $formula,
                    'new_amount' => $newAmount,
                    'component_values_used' => $componentValues
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error("Failed to recalculate component with formula", [
                    'slip' => $slip['name'],
                    'component' => $componentName,
                    'formula' => $formula,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Recalculer les totaux finaux
        $this->recalculateSalarySlipTotals($slip);
    }
    
    /**
     * Récupère les valeurs actuelles de tous les composants
     */
    private function getComponentValues(array $slip): array
    {
        $values = [];
        
        // Récupérer les valeurs des earnings
        if (isset($slip['earnings'])) {
            foreach ($slip['earnings'] as $earning) {
                $abbr = $earning['abbr'] ?? null;
                if ($abbr) {
                    $values[$abbr] = $earning['amount'];
                }
            }
        }
        
        // Récupérer les valeurs des deductions
        if (isset($slip['deductions'])) {
            foreach ($slip['deductions'] as $deduction) {
                $abbr = $deduction['abbr'] ?? null;
                if ($abbr) {
                    $values[$abbr] = $deduction['amount'];
                }
            }
        }
        
        return $values;
    }
    
    /**
     * Récupère l'abréviation d'un composant
     */
    private function getComponentAbbreviation(array $slip, string $componentName): ?string
    {
        // Chercher dans les earnings
        if (isset($slip['earnings'])) {
            foreach ($slip['earnings'] as $earning) {
                if ($earning['salary_component'] === $componentName) {
                    return $earning['abbr'] ?? null;
                }
            }
        }
        
        // Chercher dans les deductions
        if (isset($slip['deductions'])) {
            foreach ($slip['deductions'] as $deduction) {
                if ($deduction['salary_component'] === $componentName) {
                    return $deduction['abbr'] ?? null;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Évalue une formule avec les valeurs des composants
     */
    private function evaluateFormula(string $formula, array $componentValues): float
    {
        // Remplacer les abréviations par leurs valeurs dans la formule
        $evaluableFormula = $formula;
        foreach ($componentValues as $abbr => $value) {
            $evaluableFormula = str_replace($abbr, (string)$value, $evaluableFormula);
        }
        
        // Sécurité : vérifier que la formule ne contient que des opérations mathématiques autorisées
        if (!preg_match('/^[0-9+\-*\/\(\)\.\s]+$/', $evaluableFormula)) {
            throw new \InvalidArgumentException("Formula contains invalid characters: " . $evaluableFormula);
        }
        
        // Évaluer la formule
        $result = eval("return $evaluableFormula;");
        
        if ($result === false || !is_numeric($result)) {
            throw new \RuntimeException("Formula evaluation failed: " . $evaluableFormula);
        }
        
        return (float)$result;
    }
    
    /**
     * Met à jour le montant d'un composant dans la fiche de paie
     */
    private function updateComponentAmount(array &$slip, string $componentName, float $newAmount): void
    {
        // Mettre à jour dans les earnings
        if (isset($slip['earnings'])) {
            foreach ($slip['earnings'] as $index => $earning) {
                if ($earning['salary_component'] === $componentName) {
                    $oldAmount = $earning['amount'];
                    $slip['earnings'][$index]['amount'] = $newAmount;
                    
                    $this->logger->info("Updated earning component amount", [
                        'slip' => $slip['name'],
                        'component' => $componentName,
                        'old_amount' => $oldAmount,
                        'new_amount' => $newAmount
                    ]);
                    return;
                }
            }
        }
        
        // Mettre à jour dans les deductions
        if (isset($slip['deductions'])) {
            foreach ($slip['deductions'] as $index => $deduction) {
                if ($deduction['salary_component'] === $componentName) {
                    $oldAmount = $deduction['amount'];
                    $slip['deductions'][$index]['amount'] = $newAmount;
                    
                    $this->logger->info("Updated deduction component amount", [
                        'slip' => $slip['name'],
                        'component' => $componentName,
                        'old_amount' => $oldAmount,
                        'new_amount' => $newAmount
                    ]);
                    return;
                }
            }
        }
    }
    /**
     * Valide la cohérence des totaux de la fiche de paie
     */
    private function validateSalarySlipTotals(array &$slip): void
    {
        $calculatedEarnings = 0;
        $calculatedDeductions = 0;
        
        // Calculer les totaux attendus
        if (isset($slip['earnings'])) {
            foreach ($slip['earnings'] as $earning) {
                $calculatedEarnings += $earning['amount'] ?? 0;
            }
        }
        
        if (isset($slip['deductions'])) {
            foreach ($slip['deductions'] as $deduction) {
                $calculatedDeductions += $deduction['amount'] ?? 0;
            }
        }
        
        $calculatedNetPay = $calculatedEarnings - $calculatedDeductions;
        
        // Vérifier la cohérence avec une tolérance de 0.01
        $tolerance = 0.01;
        
        if (abs(($slip['gross_pay'] ?? 0) - $calculatedEarnings) > $tolerance) {
            $this->logger->warning("Gross pay mismatch detected, correcting", [
                'slip' => $slip['name'],
                'stored_gross_pay' => $slip['gross_pay'] ?? 0,
                'calculated_earnings' => $calculatedEarnings
            ]);
            $slip['gross_pay'] = $calculatedEarnings;
        }
        
        if (abs(($slip['total_deduction'] ?? 0) - $calculatedDeductions) > $tolerance) {
            $this->logger->warning("Total deduction mismatch detected, correcting", [
                'slip' => $slip['name'],
                'stored_total_deduction' => $slip['total_deduction'] ?? 0,
                'calculated_deductions' => $calculatedDeductions
            ]);
            $slip['total_deduction'] = $calculatedDeductions;
        }
        
        if (abs(($slip['net_pay'] ?? 0) - $calculatedNetPay) > $tolerance) {
            $this->logger->warning("Net pay mismatch detected, correcting", [
                'slip' => $slip['name'],
                'stored_net_pay' => $slip['net_pay'] ?? 0,
                'calculated_net_pay' => $calculatedNetPay
            ]);
            $slip['net_pay'] = $calculatedNetPay;
        }
        
        // S'assurer que tous les champs de base sont cohérents
        $slip['base_gross_pay'] = $slip['gross_pay'];
        $slip['base_total_deduction'] = $slip['total_deduction'];
        $slip['base_net_pay'] = $slip['net_pay'];
        $slip['rounded_total'] = $slip['net_pay'];
        $slip['base_rounded_total'] = $slip['net_pay'];
    }

    /**
     * Recalcule les totaux de la fiche de paie
     */
    private function recalculateSalarySlipTotals(array &$slip): void
    {
        $totalEarnings = 0;
        $totalDeductions = 0;
        $earningsDetails = [];
        $deductionsDetails = [];
        
        // Calculer le total des gains avec détails
        if (isset($slip['earnings'])) {
            foreach ($slip['earnings'] as $earning) {
                $amount = $earning['amount'] ?? 0;
                $totalEarnings += $amount;
                $earningsDetails[] = [
                    'component' => $earning['salary_component'] ?? 'Unknown',
                    'amount' => $amount
                ];
            }
        }
        
        // Calculer le total des déductions avec détails
        if (isset($slip['deductions'])) {
            foreach ($slip['deductions'] as $deduction) {
                $amount = $deduction['amount'] ?? 0;
                $totalDeductions += $amount;
                $deductionsDetails[] = [
                    'component' => $deduction['salary_component'] ?? 'Unknown',
                    'amount' => $amount
                ];
            }
        }
        
        // Mettre à jour les totaux dans la fiche de paie
        $oldGrossPay = $slip['gross_pay'] ?? 0;
        $oldNetPay = $slip['net_pay'] ?? 0;
        $oldTotalDeduction = $slip['total_deduction'] ?? 0;
        
        $slip['gross_pay'] = $totalEarnings;
        $slip['base_gross_pay'] = $totalEarnings;
        $slip['total_deduction'] = $totalDeductions;
        $slip['base_total_deduction'] = $totalDeductions;
        $slip['net_pay'] = $totalEarnings - $totalDeductions;
        $slip['base_net_pay'] = $totalEarnings - $totalDeductions;
        $slip['rounded_total'] = $totalEarnings - $totalDeductions;
        $slip['base_rounded_total'] = $totalEarnings - $totalDeductions;
        
        $this->logger->info("Recalculated salary slip totals", [
            'slip' => $slip['name'],
            'earnings_details' => $earningsDetails,
            'deductions_details' => $deductionsDetails,
            'total_earnings_calculated' => $totalEarnings,
            'total_deductions_calculated' => $totalDeductions,
            'net_pay_calculated' => $totalEarnings - $totalDeductions,
            'old_gross_pay' => $oldGrossPay,
            'new_gross_pay' => $slip['gross_pay'],
            'old_total_deduction' => $oldTotalDeduction,
            'new_total_deduction' => $slip['total_deduction'],
            'old_net_pay' => $oldNetPay,
            'new_net_pay' => $slip['net_pay']
        ]);
    }
}