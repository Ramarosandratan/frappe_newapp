<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class StatsController extends AbstractController
{
    public function __construct(private readonly ErpNextService $erpNextService)
    {
    }

    #[Route('/stats', name: 'app_stats')]
    public function index(Request $request, ChartBuilderInterface $chartBuilder): Response
    {
        $currentYear = date('Y');
        $selectedYear = $request->query->getInt('year', $currentYear);

        $years = range($currentYear, $currentYear - 5);

        $salarySlips = $this->erpNextService->getAllSalarySlips($selectedYear);

        $statsByMonth = [];
        $allComponents = [];

        foreach ($salarySlips as $slip) {
            $month = date('Y-m', strtotime($slip['start_date']));
            $startDate = date('Y-m-01', strtotime($slip['start_date']));
            $endDate = date('Y-m-t', strtotime($slip['start_date']));

            if (!isset($statsByMonth[$month])) {
                $statsByMonth[$month] = [
                    'gross_pay' => 0,
                    'net_pay' => 0,
                    'total_deduction' => 0,
                    'components' => [],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ];
            }

            $statsByMonth[$month]['gross_pay'] += $slip['gross_pay'];
            $statsByMonth[$month]['net_pay'] += $slip['net_pay'];
            $statsByMonth[$month]['total_deduction'] += $slip['total_deduction'];

            foreach ($slip['earnings'] as $earning) {
                $component = $earning['salary_component'];
                if (!isset($statsByMonth[$month]['components'][$component])) {
                    $statsByMonth[$month]['components'][$component] = 0;
                }
                $statsByMonth[$month]['components'][$component] += $earning['amount'];
                $allComponents[$component] = true;
            }

            foreach ($slip['deductions'] as $deduction) {
                $component = $deduction['salary_component'];
                if (!isset($statsByMonth[$month]['components'][$component])) {
                    $statsByMonth[$month]['components'][$component] = 0;
                }
                $statsByMonth[$month]['components'][$component] += $deduction['amount'];
                $allComponents[$component] = true;
            }
        }

        ksort($statsByMonth);
        ksort($allComponents);

        $labels = [];
        $grossPayData = [];
        $netPayData = [];

        $monthFormatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMM');

        foreach ($statsByMonth as $month => $stats) {
            $labels[] = ucfirst($monthFormatter->format(new \DateTime($month)));
            $grossPayData[] = $stats['gross_pay'];
            $netPayData[] = $stats['net_pay'];
        }

        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Salaire Brut',
                    'backgroundColor' => 'rgba(255, 99, 132, .4)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => $grossPayData,
                    'tension' => 0.2,
                ],
                [
                    'label' => 'Salaire Net',
                    'backgroundColor' => 'rgba(54, 162, 235, .4)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'data' => $netPayData,
                    'tension' => 0.2,
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
        ]);

        return $this->render('stats/index.html.twig', [
            'statsByMonth' => $statsByMonth,
            'allComponents' => array_keys($allComponents),
            'years' => $years,
            'selectedYear' => $selectedYear,
            'chart' => $chart,
        ]);
    }

    #[Route('/stats/monthly-detail/{startDate}/{endDate}', name: 'app_stats_monthly_detail')]
    public function monthlyDetail(string $startDate, string $endDate): Response
    {
        $salarySlips = $this->erpNextService->getSalarySlipsByPeriod($startDate, $endDate);

        $monthFormatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
        $monthTitle = ucfirst($monthFormatter->format(new \DateTime($startDate)));

        return $this->render('stats/monthly_detail.html.twig', [
            'salarySlips' => $salarySlips,
            'monthTitle' => $monthTitle,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    #[Route('/stats/monthly-summary', name: 'app_stats_monthly_summary')]
    public function monthlySummary(Request $request): Response
    {
        $currentYear = date('Y');
        $currentMonth = date('m');

        $selectedYear = $request->query->getInt('year', $currentYear);
        $selectedMonth = $request->query->getInt('month', $currentMonth);

        $startDate = (new \DateTimeImmutable("{$selectedYear}-{$selectedMonth}-01"))->format('Y-m-d');
        $endDate = (new \DateTimeImmutable("{$selectedYear}-{$selectedMonth}-01"))->format('Y-m-t');

        $salarySlips = $this->erpNextService->getSalarySlipsByPeriod($startDate, $endDate);

        $summaryByEmployee = [];
        $allComponents = [];

        foreach ($salarySlips as $slip) {
            $employeeName = $slip['employee_name'];
            if (!isset($summaryByEmployee[$employeeName])) {
                $summaryByEmployee[$employeeName] = [
                    'gross_pay' => 0,
                    'net_pay' => 0,
                    'total_deduction' => 0,
                    'components' => [],
                ];
            }

            $summaryByEmployee[$employeeName]['gross_pay'] += $slip['gross_pay'];
            $summaryByEmployee[$employeeName]['net_pay'] += $slip['net_pay'];
            $summaryByEmployee[$employeeName]['total_deduction'] += $slip['total_deduction'];

            foreach ($slip['earnings'] as $earning) {
                $component = $earning['salary_component'];
                if (!isset($summaryByEmployee[$employeeName]['components'][$component])) {
                    $summaryByEmployee[$employeeName]['components'][$component] = 0;
                }
                $summaryByEmployee[$employeeName]['components'][$component] += $earning['amount'];
                $allComponents[$component] = true;
            }

            foreach ($slip['deductions'] as $deduction) {
                $component = $deduction['salary_component'];
                if (!isset($summaryByEmployee[$employeeName]['components'][$component])) {
                    $summaryByEmployee[$employeeName]['components'][$component] = 0;
                }
                $summaryByEmployee[$employeeName]['components'][$component] += $deduction['amount'];
                $allComponents[$component] = true;
            }
        }

        ksort($summaryByEmployee);
        ksort($allComponents);

        $monthFormatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
        $monthTitle = ucfirst($monthFormatter->format(new \DateTime("{$selectedYear}-{$selectedMonth}-01")));

        $years = range($currentYear, $currentYear - 5);
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = ucfirst($monthFormatter->format(new \DateTime("2000-{$i}-01")));
        }

        return $this->render('stats/monthly_summary.html.twig', [
            'summaryByEmployee' => $summaryByEmployee,
            'allComponents' => array_keys($allComponents),
            'monthTitle' => $monthTitle,
            'years' => $years,
            'months' => $months,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
        ]);
    }
}
