<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour la gestion des employés.
 * Gère l'affichage des listes d'employés et des détails d'employés individuels.
 */
class EmployeeController extends AbstractController
{
    /**
     * Constructeur du contrôleur.
     *
     * @param ErpNextService $erpNextService Service pour interagir avec l'API ERPNext.
     * @param LoggerInterface $logger Interface de journalisation pour les messages de débogage et d'erreur.
     */
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Affiche la liste des employés.
     *
     * Cette méthode récupère une liste d'employés depuis le service ERPNext.
     * Elle supporte une fonctionnalité de recherche via le paramètre de requête 'search'.
     *
     * @param Request $request L'objet requête HTTP.
     * @return Response La réponse HTTP rendant la vue de la liste des employés.
     */
    #[Route('/employees', name: 'app_employee_list')]
    public function list(Request $request): Response
    {
        // Récupère le terme de recherche depuis les paramètres de la requête.
        $search = $request->query->get('search');
        // Récupère les employés via le service ERPNext, en appliquant le filtre de recherche si présent.
        $employees = $this->erpNextService->getEmployees($search);

        // Rend la vue 'employee/list.html.twig' en passant les employés et le terme de recherche.
        return $this->render('employee/list.html.twig', [
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    /**
     * Affiche les détails d'un employé spécifique.
     *
     * Cette méthode récupère les informations d'un employé et ses fiches de paie associées
     * en utilisant l'ID de l'employé. Si l'employé n'est pas trouvé, une exception NotFoundHttpException est levée.
     *
     * @param string $id L'ID unique de l'employé.
     * @return Response La réponse HTTP rendant la vue des détails de l'employé.
     * @throws NotFoundHttpException Si l'employé avec l'ID donné n'est pas trouvé.
     */
    #[Route('/employee/{id}', name: 'app_employee_detail', requirements: ['id' => '.+'])]
    public function detail(string $id): Response
    {
        // Récupère les détails de l'employé via le service ERPNext.
        $employee = $this->erpNextService->getEmployee($id);

        // Si l'employé n'est pas trouvé, lève une exception HTTP 404.
        if (!$employee) {
            throw new NotFoundHttpException('Employee not found');
        }

        // Récupère les fiches de paie pour l'employé spécifié.
        $salarySlips = $this->erpNextService->getSalarySlipsForEmployee($id);
        // Journalise les données des fiches de paie pour le débogage avant le rendu.
        $this->logger->debug('EmployeeController: Salary slips data before rendering', ['employeeId' => $id, 'salarySlips' => $salarySlips]);

        // Rend la vue 'employee/detail.html.twig' en passant les détails de l'employé et ses fiches de paie.
        return $this->render('employee/detail.html.twig', [
            'employee' => $employee,
            'salary_slips' => $salarySlips,
        ]);
    }
}
