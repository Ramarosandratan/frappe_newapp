<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class EmployeeController extends AbstractController
{
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/employees', name: 'app_employee_list')]
    public function list(Request $request): Response
    {
        $search = $request->query->get('search');
        $employees = $this->erpNextService->getEmployees($search);

        return $this->render('employee/list.html.twig', [
            'employees' => $employees,
            'search' => $search,
        ]);
    }

    #[Route('/employee/{id}', name: 'app_employee_detail')]
    public function detail(string $id): Response
    {
        $employee = $this->erpNextService->getEmployee($id);

        if (!$employee) {
            throw new NotFoundHttpException('Employee not found');
        }

        $salarySlips = $this->erpNextService->getSalarySlipsForEmployee($id);
        $this->logger->debug('EmployeeController: Salary slips data before rendering', ['employeeId' => $id, 'salarySlips' => $salarySlips]);

        return $this->render('employee/detail.html.twig', [
            'employee' => $employee,
            'salary_slips' => $salarySlips,
        ]);
    }
}
