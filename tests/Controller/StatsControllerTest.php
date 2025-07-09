<?php

namespace App\Tests\Controller;

use App\Service\ErpNextService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StatsControllerTest extends WebTestCase
{
    private function createAuthenticatedClient()
    {
        $client = static::createClient();
        
        // Mock du service ERPNext pour éviter les appels API réels
        $erpNextService = $this->createMock(ErpNextService::class);
        $erpNextService->method('getAllSalarySlips')->willReturn([
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
        ]);
        
        static::getContainer()->set(ErpNextService::class, $erpNextService);
        
        return $client;
    }

    public function testStatsControllerDataProcessing(): void
    {
        $client = $this->createAuthenticatedClient();
        
        // Simuler une session utilisateur authentifié
        $client->loginUser(new \App\Security\User('test@example.com', []));
        
        $crawler = $client->request('GET', '/stats');
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Statistiques des Salaires');
    }

    public function testChartDataStructure(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->loginUser(new \App\Security\User('test@example.com', []));
        
        $crawler = $client->request('GET', '/stats');
        
        // Vérifier que le graphique est présent
        $this->assertSelectorExists('[data-controller*="chart"]');
        
        // Vérifier que les contrôles sont présents
        $this->assertSelectorExists('#chartType');
        $this->assertSelectorExists('#componentCheckboxes');
        
        // Vérifier que les données JavaScript sont présentes
        $scriptContent = $crawler->filter('script')->text();
        $this->assertStringContainsString('formatMonthYear', $scriptContent);
    }

    public function testChartDataFormat(): void
    {
        // Test direct du contrôleur pour vérifier le format des données
        $container = static::getContainer();
        $erpNextService = $this->createMock(ErpNextService::class);
        $erpNextService->method('getAllSalarySlips')->willReturn([
            [
                'name' => 'Test Slip',
                'start_date' => '2025-04-01',
                'gross_pay' => 1000000.0,
                'net_pay' => 800000.0,
                'earnings' => [
                    ['salary_component' => 'Base', 'amount' => 1000000.0]
                ],
                'deductions' => [
                    ['salary_component' => 'Tax', 'amount' => 200000.0]
                ]
            ]
        ]);
        
        $container->set(ErpNextService::class, $erpNextService);
        
        $client = static::createClient();
        $client->loginUser(new \App\Security\User('test@example.com', []));
        
        $crawler = $client->request('GET', '/stats');
        
        // Extraire les données JavaScript pour vérification
        $scriptContent = $crawler->filter('script')->text();
        
        // Vérifier que les données sont correctement formatées
        $this->assertStringContainsString('2025-04', $scriptContent, 'Les données mensuelles doivent être présentes');
        $this->assertStringContainsString('Base', $scriptContent, 'Les composants de salaire doivent être présents');
    }
}