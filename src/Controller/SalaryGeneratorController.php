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
                $data['overwrite'],
                $data['useAverage']
            );

            $this->addFlash(
                'success',
                sprintf(
                    '%d fiches de paie créées, %d ignorées.',
                    $summary['created'],
                    $summary['skipped']
                )
            );

            return $this->redirectToRoute('app_salary_generator');
        }

        return $this->render('salary_generator/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
