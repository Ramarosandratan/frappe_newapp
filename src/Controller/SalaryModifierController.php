<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class SalaryModifierController extends AbstractController
{
    private $erpNextService;
    private $logger;

    public function __construct(ErpNextService $erpNextService, LoggerInterface $logger)
    {
        $this->erpNextService = $erpNextService;
        $this->logger = $logger;
    }

    #[Route('/salary/modifier', name: 'app_salary_modifier')]
    public function index(Request $request): Response
    {
        // Récupérer tous les composants de salaire disponibles
        $salaryComponents = $this->erpNextService->getSalaryComponents();
        
        // Conditions disponibles
        $conditions = [
            '=' => 'Égal à',
            '>' => 'Supérieur à',
            '<' => 'Inférieur à',
            '>=' => 'Supérieur ou égal à',
            '<=' => 'Inférieur ou égal à',
            '!=' => 'Différent de'
        ];
        
        // Si le formulaire est soumis
        if ($request->isMethod('POST')) {
            try {
                // Récupérer les paramètres du formulaire
                $component = $request->request->get('component');
                $condition = $request->request->get('condition');
                $conditionValue = $request->request->get('condition_value');
                $newValue = $request->request->get('new_value');
                $startDate = $request->request->get('start_date');
                $endDate = $request->request->get('end_date');
                
                // Valider les entrées
                if (!$component || !$condition || $conditionValue === null || $newValue === null) {
                    throw new \InvalidArgumentException("Tous les champs sont requis");
                }
                
                // Convertir en nombres si nécessaire
                $conditionValue = is_numeric($conditionValue) ? (float)$conditionValue : $conditionValue;
                $newValue = is_numeric($newValue) ? (float)$newValue : $newValue;
                
                // Récupérer toutes les fiches de paie pour la période spécifiée
                $startDateTime = new \DateTime($startDate);
                $endDateTime = new \DateTime($endDate);
                
                $salarySlips = $this->erpNextService->getSalarySlipsByPeriod(
                    $startDateTime->format('Y-m-d'),
                    $endDateTime->format('Y-m-d')
                );
                
                $modifiedCount = 0;
                $skippedCount = 0;
                $errorCount = 0;
                
                foreach ($salarySlips as $slip) {
                    try {
                        $modified = false;
                        
                        // Vérifier si le composant existe dans les gains
                        if (isset($slip['earnings'])) {
                            foreach ($slip['earnings'] as $index => $earning) {
                                if ($earning['salary_component'] === $component) {
                                    // Vérifier la condition
                                    $currentValue = $earning['amount'];
                                    
                                    if ($this->checkCondition($currentValue, $condition, $conditionValue)) {
                                        // Modifier la valeur
                                        $slip['earnings'][$index]['amount'] = $newValue;
                                        $modified = true;
                                    }
                                }
                            }
                        }
                        
                        // Vérifier si le composant existe dans les déductions
                        if (isset($slip['deductions'])) {
                            foreach ($slip['deductions'] as $index => $deduction) {
                                if ($deduction['salary_component'] === $component) {
                                    // Vérifier la condition
                                    $currentValue = $deduction['amount'];
                                    
                                    if ($this->checkCondition($currentValue, $condition, $conditionValue)) {
                                        // Modifier la valeur
                                        $slip['deductions'][$index]['amount'] = $newValue;
                                        $modified = true;
                                    }
                                }
                            }
                        }
                        
                        // Si des modifications ont été apportées, mettre à jour la fiche de paie
                        if ($modified) {
                            $this->erpNextService->updateSalarySlip($slip);
                            $modifiedCount++;
                        } else {
                            $skippedCount++;
                        }
                    } catch (\Exception $e) {
                        $this->logger->error("Failed to modify salary slip", [
                            'slip' => $slip['name'],
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        $errorCount++;
                    }
                }
                
                // Afficher un message de succès
                if ($modifiedCount > 0) {
                    $this->addFlash('success', sprintf(
                        '%d fiches de paie modifiées avec succès. %d ignorées. %d erreurs.',
                        $modifiedCount,
                        $skippedCount,
                        $errorCount
                    ));
                } else {
                    $this->addFlash('warning', sprintf(
                        'Aucune fiche de paie modifiée. %d ignorées. %d erreurs.',
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
            'conditions' => $conditions
        ]);
    }
    
    /**
     * Vérifie si une valeur respecte une condition
     */
    private function checkCondition($value, $condition, $conditionValue): bool
    {
        switch ($condition) {
            case '=':
                return $value == $conditionValue;
            case '>':
                return $value > $conditionValue;
            case '<':
                return $value < $conditionValue;
            case '>=':
                return $value >= $conditionValue;
            case '<=':
                return $value <= $conditionValue;
            case '!=':
                return $value != $conditionValue;
            default:
                return false;
        }
    }
}