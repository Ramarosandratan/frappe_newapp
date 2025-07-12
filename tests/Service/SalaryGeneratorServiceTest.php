<?php

namespace App\Tests\Service;

use App\Service\SalaryGeneratorService;
use App\Service\ErpNextService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use DateTime;

class SalaryGeneratorServiceTest extends TestCase
{
    private SalaryGeneratorService $salaryGeneratorService;
    private ErpNextService|MockObject $erpNextService;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->erpNextService = $this->createMock(ErpNextService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->salaryGeneratorService = new SalaryGeneratorService(
            $this->erpNextService,
            $this->logger
        );
    }

    public function testGenerateWithSpecificBaseSalary(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-01-31');
        $baseSalary = 3000.0;

        // Mock des employés actifs
        $employees = [
            [
                'name' => 'EMP001',
                'employee_name' => 'John Doe',
                'company' => 'Test Company'
            ]
        ];

        $this->erpNextService
            ->expects($this->once())
            ->method('getActiveEmployees')
            ->willReturn($employees);

        // Mock de la vérification des fiches existantes
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlips')
            ->willReturn([]);

        // Mock de l'assignation de structure salariale
        $this->erpNextService
            ->expects($this->once())
            ->method('getEmployeeSalaryStructureAssignment')
            ->willReturn([
                'salary_structure' => 'Standard Salary Structure',
                'base' => 2500.0
            ]);

        // Mock pour récupérer les fiches précédentes (aucune trouvée pour le salaire spécifique)
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlipsForEmployee')
            ->willReturn([]);

        // Mock de la création de la fiche de paie
        $this->erpNextService
            ->expects($this->once())
            ->method('addSalarySlip')
            ->with($this->callback(function($data) use ($baseSalary) {
                return $data['base'] === $baseSalary;
            }))
            ->willReturn(['name' => 'SAL-2024-001']);

        $result = $this->salaryGeneratorService->generate(
            $startDate,
            $endDate,
            false,
            false,
            $baseSalary
        );

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testGenerateSkipsExistingSlips(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-01-31');

        // Mock des employés actifs
        $employees = [
            [
                'name' => 'EMP001',
                'employee_name' => 'John Doe',
                'company' => 'Test Company'
            ]
        ];

        $this->erpNextService
            ->expects($this->once())
            ->method('getActiveEmployees')
            ->willReturn($employees);

        // Mock de la vérification des fiches existantes (fiche existante trouvée)
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlips')
            ->willReturn([
                ['name' => 'SAL-2024-001']
            ]);

        // Ne devrait pas créer de nouvelle fiche
        $this->erpNextService
            ->expects($this->never())
            ->method('addSalarySlip');

        $result = $this->salaryGeneratorService->generate(
            $startDate,
            $endDate,
            false, // overwrite = false
            false
        );

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, $result['deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testGenerateOverwritesExistingSlips(): void
    {
        $startDate = new DateTime('2024-01-01');
        $endDate = new DateTime('2024-01-31');

        // Mock des employés actifs
        $employees = [
            [
                'name' => 'EMP001',
                'employee_name' => 'John Doe',
                'company' => 'Test Company'
            ]
        ];

        $this->erpNextService
            ->expects($this->once())
            ->method('getActiveEmployees')
            ->willReturn($employees);

        // Mock de la vérification des fiches existantes (fiche existante trouvée)
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlips')
            ->willReturn([
                ['name' => 'SAL-2024-001']
            ]);

        // Mock de l'assignation de structure salariale
        $this->erpNextService
            ->expects($this->once())
            ->method('getEmployeeSalaryStructureAssignment')
            ->willReturn([
                'salary_structure' => 'Standard Salary Structure',
                'base' => 2500.0
            ]);

        // Mock pour supprimer les fiches existantes
        $this->erpNextService
            ->expects($this->once())
            ->method('deleteExistingSalarySlips')
            ->with('EMP001', '2024-01-01', '2024-01-31')
            ->willReturn([
                'deleted' => ['SAL-2024-001'],
                'errors' => []
            ]);

        // Mock pour récupérer les fiches précédentes
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlipsForEmployee')
            ->willReturn([]);

        // Devrait créer une nouvelle fiche malgré l'existence d'une fiche
        $this->erpNextService
            ->expects($this->once())
            ->method('addSalarySlip')
            ->willReturn(['name' => 'SAL-2024-002']);

        $result = $this->salaryGeneratorService->generate(
            $startDate,
            $endDate,
            true, // overwrite = true
            false
        );

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(1, $result['deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testGenerateUsesLastSalaryBeforeStartDate(): void
    {
        $startDate = new DateTime('2024-02-01');
        $endDate = new DateTime('2024-02-29');

        // Mock des employés actifs
        $employees = [
            [
                'name' => 'EMP001',
                'employee_name' => 'John Doe',
                'company' => 'Test Company'
            ]
        ];

        $this->erpNextService
            ->expects($this->once())
            ->method('getActiveEmployees')
            ->willReturn($employees);

        // Mock de la vérification des fiches existantes
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlips')
            ->willReturn([]);

        // Mock de l'assignation de structure salariale
        $this->erpNextService
            ->expects($this->once())
            ->method('getEmployeeSalaryStructureAssignment')
            ->willReturn([
                'salary_structure' => 'Standard Salary Structure',
                'base' => 2500.0
            ]);

        // Mock pour récupérer les fiches précédentes (avec une fiche avant la date de début)
        $previousSlips = [
            [
                'name' => 'SAL-2024-001',
                'start_date' => '2024-01-01',
                'docstatus' => 1
            ],
            [
                'name' => 'SAL-2023-012',
                'start_date' => '2023-12-01',
                'docstatus' => 1
            ]
        ];

        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlipsForEmployee')
            ->willReturn($previousSlips);

        // Mock des détails de la dernière fiche de paie
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlipDetails')
            ->with('SAL-2024-001')
            ->willReturn([
                'base' => 2800.0,
                'earnings' => [
                    ['salary_component' => 'Basic Salary', 'amount' => 2800.0],
                    ['salary_component' => 'HRA', 'amount' => 1400.0]
                ],
                'deductions' => [
                    ['salary_component' => 'PF', 'amount' => 336.0]
                ]
            ]);

        // Mock de la création de la fiche de paie
        $this->erpNextService
            ->expects($this->once())
            ->method('addSalarySlip')
            ->with($this->callback(function($data) {
                return $data['base'] === 2800.0 && 
                       count($data['earnings']) === 2 &&
                       count($data['deductions']) === 1;
            }))
            ->willReturn(['name' => 'SAL-2024-002']);

        $result = $this->salaryGeneratorService->generate(
            $startDate,
            $endDate,
            false,
            false // useAverage = false
        );

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['deleted']);
        $this->assertEmpty($result['errors']);
    }

    public function testGenerateUsesAverageSalary(): void
    {
        $startDate = new DateTime('2024-02-01');
        $endDate = new DateTime('2024-02-29');

        // Mock des employés actifs
        $employees = [
            [
                'name' => 'EMP001',
                'employee_name' => 'John Doe',
                'company' => 'Test Company'
            ]
        ];

        $this->erpNextService
            ->expects($this->once())
            ->method('getActiveEmployees')
            ->willReturn($employees);

        // Mock de la vérification des fiches existantes
        $this->erpNextService
            ->expects($this->once())
            ->method('getSalarySlips')
            ->willReturn([]);

        // Mock de l'assignation de structure salariale
        $this->erpNextService
            ->expects($this->once())
            ->method('getEmployeeSalaryStructureAssignment')
            ->willReturn([
                'salary_structure' => 'Standard Salary Structure',
                'base' => 2500.0
            ]);

        // Mock pour récupérer les fiches précédentes
        $previousSlips = [
            [
                'name' => 'SAL-2024-001',
                'start_date' => '2024-01-01',
                'docstatus' => 1
            ],
            [
                'name' => 'SAL-2023-012',
                'start_date' => '2023-12-01',
                'docstatus' => 1
            ],
            [
                'name' => 'SAL-2023-011',
                'start_date' => '2023-11-01',
                'docstatus' => 1
            ]
        ];

        $this->erpNextService
            ->expects($this->exactly(2))
            ->method('getSalarySlipsForEmployee')
            ->willReturn($previousSlips);

        // Mock des détails de la dernière fiche de paie (pour les composants)
        $this->erpNextService
            ->expects($this->exactly(4))
            ->method('getSalarySlipDetails')
            ->willReturnCallback(function($slipName) {
                switch($slipName) {
                    case 'SAL-2024-001':
                        return [
                            'base' => 2800.0,
                            'earnings' => [['salary_component' => 'Basic Salary', 'amount' => 2800.0]],
                            'deductions' => []
                        ];
                    case 'SAL-2023-012':
                        return [
                            'base' => 2700.0,
                            'earnings' => [['salary_component' => 'Basic Salary', 'amount' => 2700.0]],
                            'deductions' => []
                        ];
                    case 'SAL-2023-011':
                        return [
                            'base' => 2600.0,
                            'earnings' => [['salary_component' => 'Basic Salary', 'amount' => 2600.0]],
                            'deductions' => []
                        ];
                    default:
                        return null;
                }
            });

        // Mock de la création de la fiche de paie
        $expectedAverage = (2800.0 + 2700.0 + 2600.0) / 3; // 2700.0
        $this->erpNextService
            ->expects($this->once())
            ->method('addSalarySlip')
            ->with($this->callback(function($data) use ($expectedAverage) {
                return abs($data['base'] - $expectedAverage) < 0.01;
            }))
            ->willReturn(['name' => 'SAL-2024-002']);

        $result = $this->salaryGeneratorService->generate(
            $startDate,
            $endDate,
            false,
            true // useAverage = true
        );

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['deleted']);
        $this->assertEmpty($result['errors']);
    }
}