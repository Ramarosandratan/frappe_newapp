<?php

namespace App\Tests\Service;

use App\Entity\MonthlyPercentage;
use App\Repository\MonthlyPercentageRepository;
use App\Service\MonthlyPercentageService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class MonthlyPercentageServiceTest extends TestCase
{
    private MonthlyPercentageService $service;
    private MockObject $repository;
    private MockObject $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MonthlyPercentageRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->service = new MonthlyPercentageService(
            $this->repository,
            $this->logger
        );
    }

    public function testApplyMonthlyPercentageWithExistingPercentage(): void
    {
        $baseValue = 1000.0;
        $month = 1;
        $component = 'Salaire de base';
        $percentage = 10.0; // 10%

        $monthlyPercentage = new MonthlyPercentage();
        $monthlyPercentage->setPercentage($percentage);

        $this->repository
            ->expects($this->once())
            ->method('findByMonthAndComponent')
            ->with($month, $component)
            ->willReturn($monthlyPercentage);

        $result = $this->service->applyMonthlyPercentage($baseValue, $month, $component);

        $this->assertEquals(1100.0, $result); // 1000 + 10% = 1100
    }

    public function testApplyMonthlyPercentageWithoutExistingPercentage(): void
    {
        $baseValue = 1000.0;
        $month = 1;
        $component = 'Salaire de base';

        $this->repository
            ->expects($this->once())
            ->method('findByMonthAndComponent')
            ->with($month, $component)
            ->willReturn(null);

        $result = $this->service->applyMonthlyPercentage($baseValue, $month, $component);

        $this->assertEquals(1000.0, $result); // Valeur inchangée
    }

    public function testApplyMonthlyPercentageWithNegativePercentage(): void
    {
        $baseValue = 1000.0;
        $month = 2;
        $component = 'Salaire de base';
        $percentage = -15.0; // -15%

        $monthlyPercentage = new MonthlyPercentage();
        $monthlyPercentage->setPercentage($percentage);

        $this->repository
            ->expects($this->once())
            ->method('findByMonthAndComponent')
            ->with($month, $component)
            ->willReturn($monthlyPercentage);

        $result = $this->service->applyMonthlyPercentage($baseValue, $month, $component);

        $this->assertEquals(850.0, $result); // 1000 - 15% = 850
    }

    public function testSaveMonthlyPercentages(): void
    {
        $component = 'Salaire de base';
        $percentages = [
            1 => '10.5',
            2 => '-5.0',
            3 => '',
            4 => '0',
            5 => '7.25'
        ];

        $this->repository
            ->expects($this->once())
            ->method('deleteByComponent')
            ->with($component);

        $this->repository
            ->expects($this->exactly(4)) // 4 appels car le mois 3 est vide
            ->method('saveOrUpdate')
            ->willReturnCallback(function($month, $comp, $percentage) use ($component) {
                $this->assertContains($month, [1, 2, 4, 5]);
                $this->assertEquals($component, $comp);
                $this->assertContains($percentage, [10.5, -5.0, 0.0, 7.25]);
                return new MonthlyPercentage();
            });

        $this->service->saveMonthlyPercentages($component, $percentages);
    }

    public function testGetMonthlyPercentages(): void
    {
        $component = 'Salaire de base';
        
        $monthlyPercentage1 = new MonthlyPercentage();
        $monthlyPercentage1->setMonth(1)->setPercentage(10.0);
        
        $monthlyPercentage2 = new MonthlyPercentage();
        $monthlyPercentage2->setMonth(3)->setPercentage(-5.0);

        $this->repository
            ->expects($this->once())
            ->method('findByComponent')
            ->with($component)
            ->willReturn([$monthlyPercentage1, $monthlyPercentage2]);

        $result = $this->service->getMonthlyPercentages($component);

        $expected = [
            1 => 10.0,
            3 => -5.0
        ];

        $this->assertEquals($expected, $result);
    }

    public function testHasMonthlyPercentages(): void
    {
        $component = 'Salaire de base';
        
        $monthlyPercentage = new MonthlyPercentage();
        
        $this->repository
            ->expects($this->once())
            ->method('findByComponent')
            ->with($component)
            ->willReturn([$monthlyPercentage]);

        $result = $this->service->hasMonthlyPercentages($component);

        $this->assertTrue($result);
    }

    public function testHasMonthlyPercentagesReturnsFalse(): void
    {
        $component = 'Salaire de base';
        
        $this->repository
            ->expects($this->once())
            ->method('findByComponent')
            ->with($component)
            ->willReturn([]);

        $result = $this->service->hasMonthlyPercentages($component);

        $this->assertFalse($result);
    }

    public function testGetMonthNames(): void
    {
        $result = $this->service->getMonthNames();

        $this->assertIsArray($result);
        $this->assertCount(12, $result);
        $this->assertEquals('Janvier', $result[1]);
        $this->assertEquals('Décembre', $result[12]);
    }
}