<?php

namespace App\Tests\Service;

use App\Service\ErpNextService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ErpNextServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ResponseInterface $response;
    private ErpNextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);

        $this->service = new ErpNextService(
            $this->httpClient,
            $this->logger,
            'http://erpnext.localhost:8000',
            '404cb9bc953e52b', // Example API key
            '113b12914e53d62' // Example API secret
        );
    }

    public function testCreateDocTypeSuccessfully(): void
    {
        $expectedData = ['name' => 'EMP-001', 'employee_name' => 'John Doe'];

        $this->response = $this->createMock(ResponseInterface::class);
        $this->response->method('getStatusCode')->willReturn(200);
        $this->response->method('toArray')->willReturn(['data' => $expectedData]);
        $this->response->method('getContent')->willReturn(json_encode(['data' => $expectedData]));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->response);

        $result = $this->service->addEmployee(['employee_name' => 'John Doe']);

        $this->assertEquals($expectedData, $result);
    }

    public function testCreateDocTypeHandlesConflict409(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DuplicateEntryError');

        $this->response = $this->createMock(ResponseInterface::class);
        $this->response->method('getStatusCode')->willReturn(409);
        $this->response->method('getContent')->willReturn(json_encode(['exception' => 'DuplicateEntryError: Employee/EMP-001 already exists']));
        $this->response->method('toArray')->willThrowException(new \Exception());

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->response);
        
        $this->service->addEmployee(['employee_name' => 'John Doe']);
    }

    public function testCreateDocTypeHandlesValidation417(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ValidationError');

        $this->response = $this->createMock(ResponseInterface::class);
        $this->response->method('getStatusCode')->willReturn(417);
        $this->response->method('getContent')->willReturn(json_encode(['exception' => 'ValidationError: Missing mandatory field']));
        $this->response->method('toArray')->willReturn(['exception' => 'ValidationError: Missing mandatory field']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($this->response);

        $this->service->addEmployee(['employee_name' => '']);
    }

    public function testGetDocNotFound404(): void
    {
        $this->response = $this->createMock(ResponseInterface::class);
        $this->response->method('getStatusCode')->willReturn(404);
        $this->response->method('getContent')->willReturn(json_encode(['data' => null]));
        $this->response->method('toArray')->willReturn(['data' => null]);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/api/method/frappe.client.get'))
            ->willReturn($this->response);

        $result = $this->service->getEmployee('non-existent-id');

        $this->assertEmpty($result);
    }
}
