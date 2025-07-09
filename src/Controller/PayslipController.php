<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PayslipController extends AbstractController
{
    public function __construct(
        private readonly ErpNextService $erpNextService,
    ) {
    }

    #[Route('/payslip/{id}', name: 'app_payslip_view')]
    public function view(string $id): Response
    {
        // Decode the URL-encoded ID to get the original salary slip ID
        $decodedId = urldecode($id);

        $payslip = $this->erpNextService->getSalarySlipDetails($decodedId);

        if (!$payslip) {
            throw new NotFoundHttpException('The salary slip does not exist');
        }

        return $this->render('payslip/view.html.twig', [
            'payslip' => $payslip,
        ]);
    }

    #[Route('/payslip/{id}/pdf', name: 'app_payslip_pdf')]
    public function pdf(
        string $id,
        #[Autowire(service: 'knp_snappy.pdf')] Pdf $knpSnappyPdf
    ): Response {
        // Decode the URL-encoded ID to get the original salary slip ID
        $decodedId = urldecode($id);

        $payslip = $this->erpNextService->getSalarySlipDetails($decodedId);

        if (!$payslip) {
            throw new NotFoundHttpException('The salary slip does not exist');
        }

        $html = $this->renderView('payslip/pdf.html.twig', [
            'payslip' => $payslip,
        ]);

        $pdfContent = $knpSnappyPdf->getOutputFromHtml($html);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fiche-de-paie.pdf"',
        ]);
    }
}
