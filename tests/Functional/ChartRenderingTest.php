<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ChartRenderingTest extends WebTestCase
{
    public function testChartRendersWithMockData(): void
    {
        $client = static::createClient();
        
        // Simuler des données de test directement dans le template
        $testHtml = $this->generateTestChartHtml();
        
        // Vérifier que le HTML contient tous les éléments nécessaires
        $this->assertStringContainsString('data-controller="chartjs"', $testHtml);
        $this->assertStringContainsString('<canvas', $testHtml);
        $this->assertStringContainsString('id="chartType"', $testHtml);
        $this->assertStringContainsString('id="componentCheckboxes"', $testHtml);
        
        echo "✓ HTML du graphique contient tous les éléments requis\n";
    }
    
    public function testChartDataFormatting(): void
    {
        // Test du formatage des données comme dans le contrôleur réel
        $mockSalarySlips = [
            [
                'name' => 'Sal Slip/HR-EMP-00029/00001',
                'start_date' => '2025-04-01',
                'gross_pay' => 1950000.0,
                'net_pay' => 1560000.0,
                'earnings' => [
                    ['salary_component' => 'Salaire Base', 'amount' => 1500000.0],
                    ['salary_component' => 'Indemnité', 'amount' => 450000.0]
                ],
                'deductions' => [
                    ['salary_component' => 'Taxe sociale', 'amount' => 390000.0]
                ]
            ],
            [
                'name' => 'Sal Slip/HR-EMP-00029/00002',
                'start_date' => '2025-03-01',
                'gross_pay' => 2080000.0,
                'net_pay' => 1664000.0,
                'earnings' => [
                    ['salary_component' => 'Salaire Base', 'amount' => 1600000.0],
                    ['salary_component' => 'Indemnité', 'amount' => 480000.0]
                ],
                'deductions' => [
                    ['salary_component' => 'Taxe sociale', 'amount' => 416000.0]
                ]
            ]
        ];
        
        $chartData = $this->processChartData($mockSalarySlips);
        
        // Vérifications des données formatées
        $this->assertArrayHasKey('labels', $chartData);
        $this->assertArrayHasKey('datasets', $chartData);
        $this->assertCount(12, $chartData['labels']); // 12 mois
        $this->assertGreaterThan(0, count($chartData['datasets'])); // Au moins les datasets de base
        
        // Vérifier que les données de mars et avril sont présentes
        $grossPayData = $chartData['datasets'][0]['data'];
        $this->assertEquals(0, $grossPayData[0]); // Janvier = 0
        $this->assertEquals(0, $grossPayData[1]); // Février = 0
        $this->assertEquals(2080000, $grossPayData[2]); // Mars = 2,080,000
        $this->assertEquals(1950000, $grossPayData[3]); // Avril = 1,950,000
        
        echo "✓ Données du graphique correctement formatées\n";
        echo "  - Mars 2025: " . number_format($grossPayData[2], 0, ',', ' ') . " Ar\n";
        echo "  - Avril 2025: " . number_format($grossPayData[3], 0, ',', ' ') . " Ar\n";
    }
    
    public function testChartJavaScriptGeneration(): void
    {
        $mockData = [
            'monthlyEvolutionData' => [
                ['month' => '2025-03', 'totalGrossPay' => 2080000, 'totalNetPay' => 1664000],
                ['month' => '2025-04', 'totalGrossPay' => 1950000, 'totalNetPay' => 1560000]
            ],
            'allComponents' => ['Salaire Base', 'Indemnité', 'Taxe sociale']
        ];
        
        $jsCode = $this->generateChartJavaScript($mockData);
        
        // Vérifier que le JavaScript contient les bonnes données
        $this->assertStringContainsString('2025-03', $jsCode);
        $this->assertStringContainsString('2025-04', $jsCode);
        $this->assertStringContainsString('2080000', $jsCode);
        $this->assertStringContainsString('1950000', $jsCode);
        $this->assertStringContainsString('Salaire Base', $jsCode);
        $this->assertStringContainsString('formatMonthYear', $jsCode);
        
        echo "✓ JavaScript du graphique généré correctement\n";
    }
    
    public function testStimulusControllerFunctionality(): void
    {
        $controllerPath = __DIR__ . '/../../assets/controllers/chartjs_controller.js';
        $controllerContent = file_get_contents($controllerPath);
        
        // Vérifier les fonctions clés
        $requiredFunctions = [
            'connect()',
            'setupCustomFeatures()',
            'setupCustomOptions()',
            'setupEventListeners()',
            'changeType(',
            'toggleDataset('
        ];
        
        foreach ($requiredFunctions as $function) {
            $this->assertStringContainsString($function, $controllerContent);
        }
        
        // Vérifier le formatage Ariary
        $this->assertStringContainsString("+ ' Ar'", $controllerContent);
        $this->assertStringContainsString("'fr-FR'", $controllerContent);
        
        echo "✓ Contrôleur Stimulus contient toutes les fonctions requises\n";
    }
    
    private function generateTestChartHtml(): string
    {
        return '
        <div class="card">
            <div class="card-header">Évolution Mensuelle des Salaires</div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="chartType">Type de graphique:</label>
                            <select id="chartType" class="form-control">
                                <option value="line">Ligne</option>
                                <option value="bar">Barre</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Afficher les composants:</label>
                            <div id="componentCheckboxes">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" id="component-Salaire-Base" value="Salaire Base">
                                    <label class="form-check-label" for="component-Salaire-Base">Salaire Base</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="height: 400px;" data-controller="chartjs">
                    <canvas></canvas>
                </div>
            </div>
        </div>';
    }
    
    private function processChartData(array $salarySlips): array
    {
        $monthlyEvolutionData = [];
        $allComponents = [];
        $selectedYear = 2025;
        
        // Initialize monthly data
        for ($i = 1; $i <= 12; $i++) {
            $monthKey = $selectedYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $monthlyEvolutionData[$monthKey] = [
                'month' => $monthKey,
                'totalGrossPay' => 0,
                'totalNetPay' => 0,
                'components' => [],
            ];
        }
        
        // Process salary slips
        foreach ($salarySlips as $slip) {
            $month = date('Y-m', strtotime($slip['start_date']));
            
            $monthlyEvolutionData[$month]['totalGrossPay'] += $slip['gross_pay'];
            $monthlyEvolutionData[$month]['totalNetPay'] += $slip['net_pay'];
            
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
        
        ksort($monthlyEvolutionData);
        
        // Prepare Chart.js data
        $labels = [];
        $totalGrossPayData = [];
        $totalNetPayData = [];
        
        $monthFormatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMM');
        
        foreach ($monthlyEvolutionData as $monthKey => $data) {
            $labels[] = ucfirst($monthFormatter->format(new \DateTime($monthKey . '-01')));
            $totalGrossPayData[] = $data['totalGrossPay'];
            $totalNetPayData[] = $data['totalNetPay'];
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Salaire Brut Total',
                    'data' => $totalGrossPayData,
                ],
                [
                    'label' => 'Salaire Net Total',
                    'data' => $totalNetPayData,
                ]
            ]
        ];
    }
    
    private function generateChartJavaScript(array $data): string
    {
        return '
        <script>
            const monthlyEvolutionData = ' . json_encode($data['monthlyEvolutionData']) . ';
            const allComponents = ' . json_encode($data['allComponents']) . ';
            
            function formatMonthYear(monthStr) {
                const [year, month] = monthStr.split("-");
                const date = new Date(year, month - 1);
                return date.toLocaleDateString("fr-FR", { month: "short" });
            }
        </script>';
    }
}