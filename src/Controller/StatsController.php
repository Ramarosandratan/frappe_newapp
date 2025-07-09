<?php

namespace App\Controller;

use App\Service\ErpNextService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
 
class StatsController extends AbstractController
{
    public function __construct(
        private readonly ErpNextService $erpNextService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/stats', name: 'app_stats')]
    public function index(Request $request, ChartBuilderInterface $chartBuilder): Response
    {
        $currentYear = date('Y');
        $selectedYear = $request->query->getInt('year', $currentYear);

        $years = range($currentYear, $currentYear - 5);

        $this->logger->info('StatsController: Fetching salary slips for year', ['year' => $selectedYear]);
        $salarySlips = $this->erpNextService->getAllSalarySlips($selectedYear);
        $this->logger->info('StatsController: Received salary slips', ['count' => count($salarySlips), 'slips_sample' => array_slice($salarySlips, 0, 2)]);

        $monthlyEvolutionData = [];
        $allComponents = [];

        // Initialize monthly data for the selected year
        for ($i = 1; $i <= 12; $i++) {
            $monthKey = $selectedYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $monthlyEvolutionData[$monthKey] = [
                'month' => $monthKey,
                'totalGrossPay' => 0,
                'totalNetPay' => 0,
                'components' => [],
            ];
        }

        foreach ($salarySlips as $index => $slip) {
            $this->logger->debug('StatsController: Processing salary slip', ['index' => $index, 'slip_name' => $slip['name'] ?? 'N/A', 'start_date' => $slip['start_date'] ?? 'N/A', 'gross_pay' => $slip['gross_pay'] ?? 'N/A']);
            $month = date('Y-m', strtotime($slip['start_date']));

            if (!isset($monthlyEvolutionData[$month])) {
                // This should ideally not happen if getAllSalarySlips is filtered by year
                // but as a safeguard, initialize if a month outside the loop's range appears
                $monthlyEvolutionData[$month] = [
                    'month' => $month,
                    'totalGrossPay' => 0,
                    'totalNetPay' => 0,
                    'components' => [],
                ];
            }

            $monthlyEvolutionData[$month]['totalGrossPay'] += $slip['gross_pay'];
            $monthlyEvolutionData[$month]['totalNetPay'] += $slip['net_pay'];

            // Process earnings
            if (isset($slip['earnings']) && is_array($slip['earnings'])) {
                foreach ($slip['earnings'] as $earning) {
                    $componentName = $earning['salary_component'];
                    $amount = (float)($earning['amount'] ?? 0);

                    if (!isset($monthlyEvolutionData[$month]['components'][$componentName])) {
                        $monthlyEvolutionData[$month]['components'][$componentName] = 0;
                    }
                    $monthlyEvolutionData[$month]['components'][$componentName] += $amount;
                    $allComponents[$componentName] = true;
                }
            }

            // Process deductions
            if (isset($slip['deductions']) && is_array($slip['deductions'])) {
                foreach ($slip['deductions'] as $deduction) {
                    $componentName = $deduction['salary_component'];
                    $amount = (float)($deduction['amount'] ?? 0);

                    if (!isset($monthlyEvolutionData[$month]['components'][$componentName])) {
                        $monthlyEvolutionData[$month]['components'][$componentName] = 0;
                    }
                    $monthlyEvolutionData[$month]['components'][$componentName] += $amount;
                    $allComponents[$componentName] = true;
                }
            }
        }

        ksort($monthlyEvolutionData); // Sort by month (YYYY-MM)
        ksort($allComponents); // Sort components alphabetically

        // Check if we have any data
        $hasData = false;
        foreach ($monthlyEvolutionData as $data) {
            if ($data['totalGrossPay'] > 0 || $data['totalNetPay'] > 0) {
                $hasData = true;
                break;
            }
        }

        $chart = null;
        if ($hasData) {
            // Prepare data for Chart.js
            $labels = [];
            $totalGrossPayData = [];
            $totalNetPayData = [];
            $componentDataSets = [];

            $monthFormatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMM');

            foreach ($monthlyEvolutionData as $monthKey => $data) {
                $labels[] = ucfirst($monthFormatter->format(new \DateTime($monthKey . '-01')));
                $totalGrossPayData[] = $data['totalGrossPay'];
                $totalNetPayData[] = $data['totalNetPay'];

                foreach ($allComponents as $componentName => $value) {
                    if (!isset($componentDataSets[$componentName])) {
                        $componentDataSets[$componentName] = [
                            'label' => $componentName,
                            'data' => [],
                            'tension' => 0.2,
                        ];
                    }
                    // Ensure data point exists for each month, even if 0
                    $componentDataSets[$componentName]['data'][] = $data['components'][$componentName] ?? 0;
                }
            }

            $datasets = [
                [
                    'label' => 'Salaire Brut Total',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.4)',
                    'borderColor' => 'rgb(255, 99, 132)',
                    'data' => $totalGrossPayData,
                    'tension' => 0.2,
                    'fill' => false,
                ],
                [
                    'label' => 'Salaire Net Total',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.4)',
                    'borderColor' => 'rgb(54, 162, 235)',
                    'data' => $totalNetPayData,
                    'tension' => 0.2,
                    'fill' => false,
                ],
            ];

            // Add component datasets
            $colors = [
                ['rgba(75, 192, 192, 0.2)', 'rgb(75, 192, 192)'],
                ['rgba(153, 102, 255, 0.2)', 'rgb(153, 102, 255)'],
                ['rgba(255, 159, 64, 0.2)', 'rgb(255, 159, 64)'],
                ['rgba(199, 199, 199, 0.2)', 'rgb(199, 199, 199)'],
                ['rgba(83, 102, 255, 0.2)', 'rgb(83, 102, 255)'],
            ];
            $colorIndex = 0;

            foreach ($componentDataSets as $componentName => $dataSet) {
                $color = $colors[$colorIndex % count($colors)];
                $dataSet['backgroundColor'] = $color[0];
                $dataSet['borderColor'] = $color[1];
                $dataSet['hidden'] = true; // Hide component lines by default
                $datasets[] = $dataSet;
                $colorIndex++;
            }

            $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
            $chart->setData([
                'labels' => $labels,
                'datasets' => $datasets,
            ]);

            $chart->setOptions([
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'plugins' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Évolution Mensuelle des Salaires et Composants',
                        'font' => [
                            'size' => 16,
                        ],
                    ],
                    'legend' => [
                        'display' => true,
                        'position' => 'top',
                    ],
                    'tooltip' => [
                        'mode' => 'index',
                        'intersect' => false,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'title' => [
                            'display' => true,
                            'text' => 'Montant (Ar)',
                        ],
                    ],
                    'x' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Mois',
                        ],
                    ],
                ],
            ]);
        }

        $this->logger->info('StatsController: Final monthlyEvolutionData before rendering', ['data_count' => count($monthlyEvolutionData), 'data_sample' => array_slice($monthlyEvolutionData, 0, 2)]);
        return $this->render('stats/index.html.twig', [
            'monthlyEvolutionData' => array_values($monthlyEvolutionData), // Pass as indexed array
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

            if (isset($slip['earnings']) && is_array($slip['earnings'])) {
                foreach ($slip['earnings'] as $earning) {
                    $component = $earning['salary_component'];
                    if (!isset($summaryByEmployee[$employeeName]['components'][$component])) {
                        $summaryByEmployee[$employeeName]['components'][$component] = 0;
                    }
                    $summaryByEmployee[$employeeName]['components'][$component] += $earning['amount'];
                    $allComponents[$component] = true;
                }
            }

            if (isset($slip['deductions']) && is_array($slip['deductions'])) {
                foreach ($slip['deductions'] as $deduction) {
                    $component = $deduction['salary_component'];
                    if (!isset($summaryByEmployee[$employeeName]['components'][$component])) {
                        $summaryByEmployee[$employeeName]['components'][$component] = 0;
                    }
                    $summaryByEmployee[$employeeName]['components'][$component] += $deduction['amount'];
                    $allComponents[$component] = true;
                }
            }
        }

        ksort($summaryByEmployee);
        ksort($allComponents);

        $monthFormatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM');
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
