<?php

namespace App\Controller;

use App\Form\SalaryGeneratorType;
use App\Service\SalaryGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SalaryGeneratorController extends AbstractController
{
    public function __construct(private readonly SalaryGeneratorService $salaryGeneratorService)
    {
    }

    #[Route('/salary/generator', name: 'app_salary_generator')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(SalaryGeneratorType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $summary = $this->salaryGeneratorService->generate(
                $data['startDate'],
                $data['endDate'],
                $data['overwrite'] ?? false,
                $data['useAverage'] ?? false,
                $data['baseSalary'] ?? null
            );

            // Afficher le résumé de la génération
            if ($summary['created'] > 0) {
                $this->addFlash(
                    'success',
                    sprintf(
                        '✅ %d fiche(s) de paie créée(s) avec succès.',
                        $summary['created']
                    )
                );
            }

            if ($summary['skipped'] > 0) {
                $this->addFlash(
                    'info',
                    sprintf(
                        'ℹ️ %d fiche(s) de paie ignorée(s) (déjà existante(s)).',
                        $summary['skipped']
                    )
                );
            }

            if ($summary['deleted'] > 0) {
                $this->addFlash(
                    'warning',
                    sprintf(
                        '🗑️ %d fiche(s) de paie supprimée(s) avant recréation.',
                        $summary['deleted']
                    )
                );
            }

            if (!empty($summary['errors'])) {
                foreach ($summary['errors'] as $error) {
                    $this->addFlash('error', '❌ ' . $error);
                }
            }

            // Si aucune fiche n'a été créée et qu'il n'y a pas d'erreurs spécifiques
            if ($summary['created'] === 0 && $summary['skipped'] === 0 && $summary['deleted'] === 0 && empty($summary['errors'])) {
                $this->addFlash(
                    'warning',
                    '⚠️ Aucune fiche de paie n\'a été générée. Vérifiez qu\'il y a des employés actifs et des structures salariales configurées.'
                );
            }

            return $this->redirectToRoute('app_salary_generator');
        }

        return $this->render('salary_generator/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
