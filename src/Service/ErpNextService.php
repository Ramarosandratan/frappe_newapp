<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

/**
 * Optimized ERPNext Service
 */
class ErpNextService
{
    private string $apiBase;
    private string $apiKey;
    private string $apiSecret;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        string $apiBase,
        string $apiKey,
        string $apiSecret
    ) {
        $this->apiBase = $apiBase;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    // ------------------ CORE API REQUESTS ----------------------

    private function request(string $method, string $uri, array $options = []): array
    {
        $fullUrl = $this->apiBase . $uri;

        // Explicit header merge (not recursive!)
        $userHeaders = $options['headers'] ?? [];
        $options['headers'] = array_merge([
            'Authorization' => 'token ' . $this->apiKey . ':' . $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $userHeaders);

        unset($options['headers']['Content-Length']); // Let client set this

        try {
            $response = $this->client->request($method, $fullUrl, $options);
            $statusCode = $response->getStatusCode();

            // Handle empty responses explicitly
            if ($statusCode === 404 && $method === 'GET') {
                return [];
            }

            try {
                $responseData = $response->toArray(false);
            } catch (\Throwable $e) {
                $content = $response->getContent(false);
                $this->logger->error('ERPNext: Invalid JSON response', ['content' => $content]);
                throw new \RuntimeException("Invalid API response format: {$content}");
            }

            // Detect error structure and handle
            if ($statusCode >= 400) {
                $message = $responseData['exception'] ?? ($responseData['_error_message'] ?? json_encode($responseData));
                if (str_contains($message, 'DuplicateEntryError')) {
                    throw new \RuntimeException('DuplicateEntryError: ' . $message, 409);
                }
                if (isset($responseData['exc_type']) && $responseData['exc_type'] === 'ValidationError') {
                    throw new \RuntimeException('ValidationError: ' . $message, 417);
                }
                throw new \RuntimeException('ERPNext API error: ' . $message, $statusCode);
            }

            // Flexible field fallback for message/data
            if (isset($responseData['message'])) {
                return $responseData['message'];
            } elseif (isset($responseData['data'])) {
                $this->logger->debug('ERPNext: API response data', ['data' => $responseData['data']]);
                return $responseData['data'];
            } else {
                $this->logger->debug('ERPNext: API response (raw)', ['response' => $responseData]);
                return $responseData;
            }
        } catch (ClientExceptionInterface $ce) {
            // Non-2xx with details
            $this->logger->error('ERPNext API client exception', ['url' => $fullUrl, 'error' => $ce->getMessage()]);
            throw $ce;
        } catch (\Throwable $e) {
            $this->logger->error('ERPNext API exception', ['url' => $fullUrl, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // Universal resource fetcher (DRY for get/post/list)
    private function getResource(string $doctype, string $name): ?array
    {
        try {
            $res = $this->request('GET', '/api/method/frappe.client.get', [
                'query' => [
                    'doctype' => $doctype,
                    'name'    => $name,
                ]
            ]);
            return $res ?: null;
        } catch (\Throwable $e) {
            if (
                str_contains($e->getMessage(), 'not found')
                || $e->getCode() === 404
                || str_contains($e->getMessage(), 'does not exist')
            ) {
                return null;
            }
            throw $e;
        }
    }

    // Universal resource list retriever (DRY)
    private function listResource(string $doctype, array $filters = [], array $fields = [], string $order = null, int $limit = null): array
    {
        $query = [
            'doctype' => $doctype,
        ];
        if ($filters) {
            $query['filters'] = $this->jsonFilter($filters);
        }
        if ($fields) {
            $query['fields'] = json_encode($fields);
        }
        if ($order) {
            $query['order_by'] = $order;
        }
        if ($limit) {
            $query['limit'] = $limit;
        }
        return $this->request('GET', '/api/method/frappe.client.get_list', ['query' => $query]);
    }

    // Avoid accidental double encoding (accepts array or pre-encoded JSON)
    private function jsonFilter($filters): string
    {
        if ($filters === null || $filters === '') return '[]';
        if (is_string($filters)) return $filters;
        return json_encode($filters);
    }

    // ------------- USERS / LOGIN --------------------------------

    public function login(string $email, string $password): ?array
    {
        $fullUrl = $this->apiBase . '/api/method/login';

        try {
            $response = $this->client->request('POST', $fullUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'usr' => $email,
                    'pwd' => $password,
                ],
            ]);
            $data = $response->toArray();
            if (isset($data['home_page'])) {
                $this->logger->info('User logged in', ['email' => $email]);
                return $data;
            }
            $this->logger->warning('Login failed', ['email' => $email]);
            return null;
        } catch (ClientExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            if ($statusCode === 401 || $statusCode === 417) {
                $this->logger->warning('Authentication failed', ['email' => $email, 'status' => $statusCode]);
                return null;
            }
            $this->logger->error('Client exception during login', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Login error', ['email' => $email, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function findUserByEmail(string $email): ?array
    {
        try {
            $res = $this->request('GET', "/api/resource/User/{$email}");
            return $res ?: null;
        } catch (\Throwable $e) {
            $this->logger->error("Error finding user by email", ['email' => $email, 'e' => $e]);
            return null;
        }
    }

    // ------------- COMPANY / HOLIDAY ----------------------------

    public function getCompany(string $name): ?array
    {
        return $this->getResource('Company', $name);
    }

    public function createCompany(
        string $name,
        string $abbr,
        ?string $currency = 'USD',
        ?string $country = 'Madagascar'
    ): array {
        $doc = [
            'doctype' => 'Company',
            'company_name' => $name,
            'abbr' => $abbr,
            'default_currency' => $currency,
            'country' => $country,
            'chart_of_accounts' => 'Standard',
            'enable_perpetual_inventory' => 0,
            'domain' => 'Manufacturing'
        ];

        try {
            $response = $this->request('POST', '/api/method/frappe.client.insert', [
                'json' => ['doc' => $doc]
            ]);
            if (empty($response['name'])) {
                throw new \RuntimeException('Company creation failed: invalid response');
            }
            // No redundant GET - trust POST unless ambiguous

            // Try to create a holiday list (log, don't fail if error)
            try {
                $holidayListName = "{$name} Holidays " . date('Y');
                $this->createHolidayList($holidayListName, $name);
                $this->setCompanyDefaultHolidayList($name, $holidayListName);
            } catch (\Throwable $e) {
                $this->logger->warning("Could not create/associate holiday list after company creation", ['company' => $name, 'error' => $e->getMessage()]);
            }
            return $response;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'DuplicateEntryError')) {
                throw new \RuntimeException("La société '{$name}' existe déjà");
            }
            throw new \RuntimeException("Erreur lors de la création de la société: " . $msg, $e->getCode(), $e);
        }
    }

    // Holidays
    public function createHolidayList(string $name, string $company): array
    {
        $currentYear = date('Y');
        return $this->request('POST', '/api/method/frappe.client.insert', [
            'json' => [
                'doc' => [
                    'doctype' => 'Holiday List',
                    'holiday_list_name' => $name,
                    'from_date' => "$currentYear-01-01",
                    'to_date'   => "$currentYear-12-31",
                    'company'   => $company,
                    'holidays' => [
                        ['holiday_date' => "$currentYear-01-01", 'description' => 'Nouvel An'],
                        ['holiday_date' => "$currentYear-05-01", 'description' => 'Fête du Travail'],
                        ['holiday_date' => "$currentYear-06-26", 'description' => "Fête de l'Indépendance"],
                        ['holiday_date' => "$currentYear-12-25", 'description' => 'Noël']
                    ]
                ]
            ]
        ]);
    }

    public function getHolidayList(string $name): ?array
    {
        return $this->getResource('Holiday List', $name);
    }

    public function setCompanyDefaultHolidayList(string $companyName, string $holidayListName): array
    {
        // Try set_value for field
        try {
            return $this->request('POST', '/api/method/frappe.client.set_value', [
                'json' => [
                    'doctype' => 'Company',
                    'name' => $companyName,
                    'fieldname' => 'default_holiday_list',
                    'value' => $holidayListName
                ]
            ]);
        } catch (\Throwable $e) {
            // Fallback to resave whole doc if needed
            $company = $this->getCompany($companyName);
            if (!$company) {
                throw new \RuntimeException("Company not found ($companyName)");
            }
            $company['default_holiday_list'] = $holidayListName;
            return $this->request('POST', '/api/method/frappe.client.save', [
                'json' => ['doc' => array_merge(['doctype' => 'Company'], $company)]
            ]);
        }
    }

    public function setEmployeeHolidayList(string $employeeId, string $holidayListName): array
    {
        try {
            return $this->request('POST', '/api/method/frappe.client.set_value', [
                'json' => [
                    'doctype' => 'Employee',
                    'name' => $employeeId,
                    'fieldname' => 'holiday_list',
                    'value' => $holidayListName
                ]
            ]);
        } catch (\Throwable $e) {
            $employee = $this->getEmployee($employeeId);
            if (!$employee) throw new \RuntimeException("Employee not found: $employeeId");
            $employee['holiday_list'] = $holidayListName;
            return $this->request('POST', '/api/method/frappe.client.save', [
                'json' => ['doc' => array_merge(['doctype' => 'Employee'], $employee)]
            ]);
        }
    }

    // -------------- EMPLOYEES ----------------------------------

    public function getEmployees(?string $search = null): array
    {
        $filters = $search ? [['employee_name', 'like', "%{$search}%"]] : [];
        return $this->listResource(
            'Employee',
            $filters,
            ['name','employee_name','company','date_of_joining','date_of_birth','gender']
        );
    }

    public function getEmployeeByNumber(string $employeeNumber): ?array
    {
        $res = $this->listResource('Employee', [['employee_number', '=', $employeeNumber]]);
        return $res ?: null;
    }

    public function getEmployee(string $employeeId): ?array
    {
        return $this->getResource('Employee', $employeeId);
    }

    public function addEmployee(array $data): array
    {
        $required = ['employee_number','first_name','last_name','employee_name','company'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \RuntimeException("Le champ '$field' est obligatoire pour créer un employé");
            }
        }

        $existingEmployee = $this->getEmployeeByNumber($data['employee_number']);
        if (!empty($existingEmployee)) {
            return $existingEmployee[0];
        }

        try {
            return $this->request('POST', '/api/method/frappe.client.insert', [
                'json' => ['doc' => $data]
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'DuplicateEntryError')) {
                // Brief retry loop (no sleep), up to 3 tries to allow for ERPNext delays
                for ($i = 1; $i <= 3; ++$i) {
                    $found = $this->getEmployeeByNumber($data['employee_number']);
                    if (!empty($found)) {
                        return $found[0];
                    }
                }
            }
            throw $e;
        }
    }

    // -------------- SALARY STRUCTURES --------------------------

    public function getSalaryStructures(): array
    {
        return $this->listResource(
            'Salary Structure',
            [['is_active', '=', 'Yes']],
            ['name', 'company', 'is_active', 'payroll_frequency']
        );
    }

    public function getSalaryStructure(string $name): ?array
    {
        return $this->getResource('Salary Structure', $name);
    }

    public function addSalaryStructure(array $data): array
    {
        $data = array_merge([
            'doctype' => 'Salary Structure',
            'is_active' => 'Yes',
            'payroll_frequency' => 'Monthly',
            'salary_slip_based_on_timesheet' => 0
        ], $data);

        return $this->request('POST', '/api/method/frappe.client.insert', [
            'json' => ['doc' => $data]
        ]);
    }

    public function saveSalaryStructure(array $data): array
    {
        $name = $data['name'] ?? null;
        if (!$name) throw new \RuntimeException('Salary structure name is required');
        $existing = $this->getSalaryStructure($name);

        try {
            if ($existing) {
                $fields = [
                    'company' => $data['company'] ?? $existing['company'] ?? null,
                    'is_active' => 'Yes'
                ];
                if (isset($data['earnings'])) $fields['earnings'] = $data['earnings'];
                if (isset($data['deductions'])) $fields['deductions'] = $data['deductions'];

                try {
                    $this->request('POST', '/api/method/frappe.client.set_value', [
                        'json' => [
                            'doctype' => 'Salary Structure',
                            'name' => $name,
                            'fieldname' => $fields
                        ]
                    ]);
                } catch (\Throwable $e) {
                    if (str_contains($e->getMessage(), 'TimestampMismatchError')) {
                        // Refresh and retry once
                        $latest = $this->getSalaryStructure($name);
                        if ($latest) {
                            $fields = array_merge($latest, $fields);
                            $this->request('POST', '/api/method/frappe.client.set_value', [
                                'json' => [
                                    'doctype' => 'Salary Structure',
                                    'name' => $name,
                                    'fieldname' => $fields
                                ]
                            ]);
                        } else {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }

                $submitResult = $this->submitSalaryStructure($name);
                if (!isset($submitResult['already_submitted']) || $submitResult['already_submitted'] !== true) {
                    if (!isset($submitResult['name'])) {
                        throw new \RuntimeException("Failed to submit salary structure: $name");
                    }
                }
                return ['name' => $name];
            } else {
                $payload = array_merge([
                    'doctype' => 'Salary Structure',
                    'is_active' => 'Yes',
                    'salary_slip_based_on_timesheet' => 0,
                    'payroll_frequency' => 'Monthly',
                ], $data);
                $result = $this->addSalaryStructure($payload);
                if (isset($result['name'])) {
                    $submitResult = $this->submitSalaryStructure($result['name']);
                    if (!isset($submitResult['already_submitted']) || $submitResult['already_submitted'] !== true) {
                        if (!isset($submitResult['name'])) {
                            throw new \RuntimeException("Failed to submit salary structure: " . $result['name']);
                        }
                    }
                }
                return $result;
            }
        } catch (\Throwable $e) {
            // If no permission, validation: try with a new name
            if (
                str_contains($e->getMessage(), 'No permission')
                || str_contains($e->getMessage(), 'ValidationError')
            ) {
                $payload = $data;
                $payload['name'] = $name . '_' . date('Ymd_His');
                $result = $this->addSalaryStructure($payload);
                if (isset($result['name'])) {
                    $submitResult = $this->submitSalaryStructure($result['name']);
                    if (!isset($submitResult['already_submitted']) || $submitResult['already_submitted'] !== true) {
                        if (!isset($submitResult['name'])) {
                            throw new \RuntimeException("Failed to submit salary structure: " . $result['name']);
                        }
                    }
                }
                return $result;
            }
            throw $e;
        }
    }

    public function submitSalaryStructure(string $name): array
    {
        $maxRetries = 3;
        $retryCount = 0;
        $lastException = null;
        
        while ($retryCount < $maxRetries) {
            try {
                // Ajouter un délai croissant entre les tentatives
                if ($retryCount > 0) {
                    $sleepTime = $retryCount * 2;
                    $this->logger->info("Retrying submitSalaryStructure after delay", [
                        'name' => $name,
                        'retry' => $retryCount,
                        'sleep' => $sleepTime
                    ]);
                    sleep($sleepTime);
                }
                
                $docToSubmit = ['doctype' => 'Salary Structure', 'name' => $name];
                // Si c'est une re-tentative après TimestampMismatchError, on utilise le document rafraîchi
                if ($retryCount > 0 && $lastException && str_contains($lastException->getMessage(), 'TimestampMismatchError')) {
                    $latestDoc = $this->getSalaryStructure($name);
                    if (!$latestDoc) {
                        $this->logger->error("Failed to refresh salary structure for submission during retry", ['name' => $name]);
                        throw new \RuntimeException("Failed to refresh salary structure for submission: $name");
                    }
                    $docToSubmit = array_merge($docToSubmit, $latestDoc);
                    $this->logger->info("Attempting submit with refreshed document", ['name' => $name, 'doc' => $docToSubmit]);
                }

                return $this->request('POST', '/api/method/frappe.client.submit', [
                    'json' => ['doc' => $docToSubmit]
                ]);
            } catch (\Throwable $e) {
                $lastException = $e;
                $msg = $e->getMessage();

                if (
                    str_contains($msg, 'already submitted')
                    || str_contains($msg, 'docstatus')
                    || str_contains($msg, 'Cannot edit submitted')
                ) {
                    $this->logger->info("Salary structure already submitted", ['name' => $name]);
                    return ['name' => $name, 'already_submitted' => true];
                }

                if (str_contains($msg, 'TimestampMismatchError')) {
                    $this->logger->warning("TimestampMismatchError in submitSalaryStructure, retrying", [
                        'name' => $name,
                        'retry' => $retryCount,
                        'error' => $msg
                    ]);
                    // Ne pas lancer d'exception ici, laisser la boucle retenter
                } else {
                    $this->logger->error("Error in submitSalaryStructure", [
                        'name' => $name,
                        'error' => $msg
                    ]);
                    throw $e; // Pour les autres types d'erreurs, on arrête immédiatement
                }
            }
            $retryCount++;
        }

        $this->logger->error("Max retries exceeded in submitSalaryStructure", [
            'name' => $name,
            'retries' => $maxRetries
        ]);

        throw $lastException ?? new \RuntimeException("Failed to submit salary structure after $maxRetries retries");
    }

    // -------------- SALARY COMPONENTS --------------------------

    public function getSalaryComponents(): array
    {
        return $this->listResource('Salary Component', [], ['name','salary_component_abbr','type','description']);
    }

    public function getSalaryComponent(string $name): ?array
    {
        return $this->getResource('Salary Component', $name);
    }

    public function addSalaryComponent(array $data): array
    {
        return $this->request('POST', '/api/method/frappe.client.insert', [
            'json' => ['doc' => $data]
        ]);
    }

    public function saveSalaryComponent(array $data): array
    {
        $name = $data['salary_component'] ?? null;
        if (!$name) throw new \RuntimeException('Salary component name required');
        try {
            return $this->addSalaryComponent($data);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'DuplicateEntryError')) {
                return $this->updateSalaryComponent($name, $data);
            }
            throw $e;
        }
    }

    public function updateSalaryComponent(string $name, array $data): array
    {
        if (!empty($data['formula'])) {
            $data['depends_on_payment_days'] = 0;
            $data['amount_based_on_formula'] = 1;
        }
        $existing = $this->getSalaryComponent($name);
        if ($existing) {
            $data = array_merge($existing, $data);
        }
        return $this->request('POST', '/api/method/frappe.client.save', [
            'json' => ['doc' => array_merge(['doctype' => 'Salary Component', 'name' => $name], $data)]
        ]);
    }

    // -------------- SALARY SLIPS -------------------------------

    public function getAllSalarySlips(?int $year = null): array
    {
        $filters = $year ? [
            ['start_date', '>=', "{$year}-01-01"],
            ['start_date', '<=', "{$year}-12-31"],
        ] : [];
        return $this->listResource('Salary Slip', $filters, [
            'name', 'start_date','gross_pay','total_deduction','net_pay'
        ], null, 10000);
    }

    public function getSalarySlips(array $filters): array
    {
        $erpFilters = [];
        foreach ($filters as $field => $value) {
            $erpFilters[] = [$field, '=', $value];
        }
        return $this->listResource('Salary Slip', $erpFilters, [
            'name','employee','employee_name','start_date','end_date','gross_pay','total_deduction','net_pay'
        ], 'start_date desc');
    }

    public function getSalarySlipsForEmployee(string $employeeId): array
    {
        $this->logger->info('ERPNextService: Fetching salary slips for employee', ['employeeId' => $employeeId]);
        $salarySlips = $this->listResource(
            'Salary Slip',
            [['employee','=', $employeeId]],
            ['name', 'employee','employee_name', 'start_date','end_date','gross_pay','total_deduction','net_pay'],
            'start_date desc'
        );
        $this->logger->debug('ERPNext: Salary slips for employee (after API call)', ['employeeId' => $employeeId, 'slips' => $salarySlips]);
        return $salarySlips;
    }

    public function getSalarySlipDetails(string $slipId): ?array
    {
        return $this->getResource('Salary Slip', $slipId);
    }

    public function getSalarySlipsByPeriod(string $startDate, string $endDate): array
    {
        $filters = [
            ['start_date','>=', $startDate],
            ['end_date','<=', $endDate]
        ];
        return $this->listResource('Salary Slip', $filters, [
            'name','employee','employee_name','start_date','end_date','gross_pay','total_deduction','net_pay'
        ]);
    }

    public function getEmployeeSalarySlipsByPeriod(string $employeeId, ?string $startDate = null, ?string $endDate = null): array
    {
        $filters = [['employee','=', $employeeId]];
        if ($startDate) $filters[] = ['start_date','>=', $startDate];
        if ($endDate) $filters[] = ['end_date','<=', $endDate];
        return $this->listResource('Salary Slip', $filters, [
            'name','employee','employee_name','start_date','end_date','gross_pay','total_deduction','net_pay'
        ], 'start_date desc');
    }

    public function getLastEmployeeSalarySlip(string $employeeId): ?array
    {
        $slips = $this->listResource(
            'Salary Slip',
            [['employee','=' , $employeeId]],
            ['name','employee','employee_name','start_date','end_date','gross_pay','total_deduction','net_pay'],
            'start_date desc',
            1
        );
        return $slips ? $slips[0] : null;
    }

    public function addSalarySlip(array $data): array
    {
        $dataWithDoctype = array_merge(['doctype' => 'Salary Slip'], $data);
        
        // Vérifier les champs requis
        foreach (['employee', 'start_date', 'end_date', 'salary_structure'] as $req) {
            if (empty($dataWithDoctype[$req])) {
                throw new \RuntimeException("Champ $req requis pour fiche de paie");
            }
        }
        
        if (!isset($dataWithDoctype['posting_date'])) {
            $dataWithDoctype['posting_date'] = date('Y-m-d');
        }
        
        try {
            $this->logger->info("Creating salary slip", [
                'employee' => $dataWithDoctype['employee'],
                'period' => $dataWithDoctype['start_date'] . ' to ' . $dataWithDoctype['end_date'],
                'structure' => $dataWithDoctype['salary_structure']
            ]);
            
            return $this->request('POST', '/api/method/frappe.client.insert', [
                'json' => ['doc' => $dataWithDoctype]
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            
            // Pour toutes les erreurs, on arrête immédiatement
            $this->logger->error("Error in addSalarySlip", [
                'employee' => $dataWithDoctype['employee'],
                'error' => $msg
            ]);
            throw $e;
        }
    }

    public function updateSalarySlip(array $data): array
    {
        $data['doctype'] = $data['doctype'] ?? 'Salary Slip';
        return $this->request('POST', '/api/method/frappe.client.save', [
            'json' => ['doc' => $data]
        ]);
    }

    // -------------- STRUCTURE ASSIGNMENTS ----------------------

    public function assignSalaryStructure(string $employeeId, string $salaryStructureName, string $fromDate, ?string $toDate = null): array
    {
        try {
            $employee = $this->getEmployee($employeeId);
            if (!$employee) {
                throw new \RuntimeException("Employee not found: $employeeId");
            }
            
            $company = $employee['company'] ?? null;
            $data = [
                'doctype' => 'Salary Structure Assignment',
                'employee' => $employeeId,
                'salary_structure' => $salaryStructureName,
                'from_date' => $fromDate,
                'company' => $company
            ];
            if ($toDate) $data['to_date'] = $toDate;
            
            $this->logger->info("Attempting to assign salary structure", [
                'employee' => $employeeId,
                'structure' => $salaryStructureName,
                'from_date' => $fromDate
            ]);
            
            $result = $this->request('POST', '/api/method/frappe.client.insert', [
                'json' => ['doc' => $data]
            ]);
            
            if (!empty($result['name'])) {
                $this->logger->info("Salary structure assigned, submitting", [
                    'employee' => $employeeId,
                    'structure' => $salaryStructureName,
                    'assignment' => $result['name']
                ]);
                
                $this->submitSalaryStructureAssignment($result['name']);
            }
            
            return $result;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            
            // Log the error and rethrow it without retrying
            $this->logger->error("Error in assignSalaryStructure", [
                'employee' => $employeeId,
                'structure' => $salaryStructureName,
                'error' => $msg
            ]);
            throw $e;
        }
    }

    public function getSalaryStructureAssignments(string $employeeId): array
    {
        return $this->listResource(
            'Salary Structure Assignment',
            [['employee', '=', $employeeId]],
            ['name', 'employee','employee_name','salary_structure','from_date','base','company']
        );
    }

    public function getEmployeeSalaryStructureAssignment(string $employeeId, string $date): ?array
    {
        $assignments = $this->listResource(
            'Salary Structure Assignment',
            [
                ['employee','=',$employeeId],
                ['from_date','<=',$date]
            ],
            ['name','employee','employee_name','salary_structure','from_date','company'],
            'from_date desc',
            1
        );
        return $assignments ? $assignments[0] : null;
    }

    public function submitSalaryStructureAssignment(string $name): array
    {
        $maxRetries = 3;
        $retryCount = 0;
        $lastException = null;

        while ($retryCount < $maxRetries) {
            try {
                if ($retryCount > 0) {
                    $sleepTime = $retryCount * 2;
                    $this->logger->info("Retrying submitSalaryStructureAssignment after delay", [
                        'name' => $name,
                        'retry' => $retryCount,
                        'sleep' => $sleepTime
                    ]);
                    sleep($sleepTime);
                }

                $docToSubmit = ['doctype' => 'Salary Structure Assignment', 'name' => $name];
                // Si c'est une re-tentative après TimestampMismatchError, on utilise le document rafraîchi
                if ($retryCount > 0 && $lastException && str_contains($lastException->getMessage(), 'TimestampMismatchError')) {
                    $latestDoc = $this->getResource('Salary Structure Assignment', $name);
                    if (!$latestDoc) {
                        $this->logger->error("Failed to refresh salary structure assignment for submission during retry", ['name' => $name]);
                        throw new \RuntimeException("Failed to refresh salary structure assignment for submission: $name");
                    }
                    $docToSubmit = array_merge($docToSubmit, $latestDoc);
                    $this->logger->info("Attempting submit with refreshed assignment document", ['name' => $name, 'doc' => $docToSubmit]);
                }

                return $this->request('POST', '/api/method/frappe.client.submit', [
                    'json' => ['doc' => $docToSubmit]
                ]);
            } catch (\Throwable $e) {
                $lastException = $e;
                $msg = $e->getMessage();

                if (
                    str_contains($msg, 'already submitted') ||
                    str_contains($msg, 'docstatus') ||
                    str_contains($msg, 'Cannot edit submitted')
                ) {
                    $this->logger->info("Salary structure assignment already submitted", ['name' => $name]);
                    return ['name' => $name, 'already_submitted' => true];
                }

                if (str_contains($msg, 'TimestampMismatchError')) {
                    $this->logger->warning("TimestampMismatchError in submitSalaryStructureAssignment, retrying", [
                        'name' => $name,
                        'retry' => $retryCount,
                        'error' => $msg
                    ]);
                    // Ne pas lancer d'exception ici, laisser la boucle retenter
                } else {
                    $this->logger->error("Error in submitSalaryStructureAssignment", [
                        'name' => $name,
                        'error' => $msg
                    ]);
                    throw $e;
                }
            }
            $retryCount++;
        }

        $this->logger->error("Max retries exceeded in submitSalaryStructureAssignment", [
            'name' => $name,
            'retries' => $maxRetries
        ]);

        throw $lastException ?? new \RuntimeException("Failed to submit salary structure assignment after $maxRetries retries");
    }
}
