<?php

namespace App\Tests\Integration;

use App\Service\MonthlyPercentageService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MonthlyPercentageIntegrationTest extends KernelTestCase
{
    private MonthlyPercentageService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = static::getContainer()->get(MonthlyPercentageService::class);
    }

    public function testCompleteWorkflow(): void
    {
        $component = 'Test Salary Component';
        
        // Test 1: Sauvegarder des pourcentages
        $percentages = [
            1 => 10.0,   // Janvier: +10%
            2 => 5.0,    // Février: +5%
            3 => -2.5,   // Mars: -2.5%
            6 => 15.0,   // Juin: +15%
            12 => -5.0   // Décembre: -5%
        ];

        $this->service->saveMonthlyPercentages($component, $percentages);

        // Test 2: Vérifier que les pourcentages ont été sauvegardés
        $this->assertTrue($this->service->hasMonthlyPercentages($component));

        // Test 3: Récupérer les pourcentages
        $savedPercentages = $this->service->getMonthlyPercentages($component);
        $this->assertEquals(10.0, $savedPercentages[1]);
        $this->assertEquals(5.0, $savedPercentages[2]);
        $this->assertEquals(-2.5, $savedPercentages[3]);
        $this->assertEquals(15.0, $savedPercentages[6]);
        $this->assertEquals(-5.0, $savedPercentages[12]);

        // Test 4: Appliquer les pourcentages
        $baseValue = 1000.0;
        
        $this->assertEquals(1100.0, $this->service->applyMonthlyPercentage($baseValue, 1, $component)); // +10%
        $this->assertEquals(1050.0, $this->service->applyMonthlyPercentage($baseValue, 2, $component)); // +5%
        $this->assertEquals(975.0, $this->service->applyMonthlyPercentage($baseValue, 3, $component));  // -2.5%
        $this->assertEquals(1000.0, $this->service->applyMonthlyPercentage($baseValue, 4, $component)); // Pas de changement
        $this->assertEquals(1150.0, $this->service->applyMonthlyPercentage($baseValue, 6, $component)); // +15%
        $this->assertEquals(950.0, $this->service->applyMonthlyPercentage($baseValue, 12, $component)); // -5%

        // Test 5: Tester avec un composant inexistant
        $this->assertFalse($this->service->hasMonthlyPercentages('Inexistant Component'));
        $this->assertEquals($baseValue, $this->service->applyMonthlyPercentage($baseValue, 1, 'Inexistant Component'));

        // Test 6: Mettre à jour les pourcentages
        $newPercentages = [
            1 => 20.0,   // Janvier: +20% (modifié)
            2 => 5.0,    // Février: +5% (inchangé)
            7 => 8.0,    // Juillet: +8% (nouveau)
        ];

        $this->service->saveMonthlyPercentages($component, $newPercentages);
        
        $updatedPercentages = $this->service->getMonthlyPercentages($component);
        $this->assertEquals(20.0, $updatedPercentages[1]);
        $this->assertEquals(5.0, $updatedPercentages[2]);
        $this->assertEquals(8.0, $updatedPercentages[7]);
        $this->assertArrayNotHasKey(3, $updatedPercentages); // Supprimé
        $this->assertArrayNotHasKey(6, $updatedPercentages); // Supprimé
        $this->assertArrayNotHasKey(12, $updatedPercentages); // Supprimé

        // Nettoyage
        $repository = static::getContainer()->get('App\Repository\MonthlyPercentageRepository');
        $repository->deleteByComponent($component);
    }

    public function testMonthNames(): void
    {
        $monthNames = $this->service->getMonthNames();
        
        $this->assertIsArray($monthNames);
        $this->assertCount(12, $monthNames);
        $this->assertEquals('Janvier', $monthNames[1]);
        $this->assertEquals('Février', $monthNames[2]);
        $this->assertEquals('Mars', $monthNames[3]);
        $this->assertEquals('Avril', $monthNames[4]);
        $this->assertEquals('Mai', $monthNames[5]);
        $this->assertEquals('Juin', $monthNames[6]);
        $this->assertEquals('Juillet', $monthNames[7]);
        $this->assertEquals('Août', $monthNames[8]);
        $this->assertEquals('Septembre', $monthNames[9]);
        $this->assertEquals('Octobre', $monthNames[10]);
        $this->assertEquals('Novembre', $monthNames[11]);
        $this->assertEquals('Décembre', $monthNames[12]);
    }
}