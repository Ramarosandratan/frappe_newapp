<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

/**
 * Service optimisé pour l'intégration avec ERPNext
 * 
 * Ce service gère toutes les communications avec l'API ERPNext :
 * - Authentification par token API
 * - Gestion des erreurs et exceptions
 * - Opérations CRUD sur les documents ERPNext
 * - Gestion spécifique des fiches de paie et composants de salaire
 * 
 * Fonctionnalités principales :
 * - Récupération et modification des fiches de paie
 * - Gestion des statuts de documents (Draft/Submitted/Cancelled)
 * - Validation des données avant sauvegarde
 * - Logs détaillés pour le débogage
 */
class ErpNextService
{
    /** @var string URL de base de l'API ERPNext */
    private string $apiBase;
    
    /** @var string Clé API pour l'authentification */
    private string $apiKey;
    
    /** @var string Secret API pour l'authentification */
    private string $apiSecret;

    /**
     * Constructeur - Configuration de l'API ERPNext
     * 
     * @param HttpClientInterface $client Client HTTP Symfony
     * @param LoggerInterface $logger Logger pour tracer les opérations
     * @param string $apiBase URL de base de l'API ERPNext
     * @param string $apiKey Clé API
     * @param string $apiSecret Secret API
     */
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

    // ================== MÉTHODES PRINCIPALES D'API ==================

    /**
     * Méthode privée pour effectuer des requêtes HTTP vers l'API ERPNext
     * 
     * Gère l'authentification, les headers, et le traitement des réponses.
     * Inclut la gestion d'erreurs spécifique à ERPNext.
     * 
     * @param string $method Méthode HTTP (GET, POST, PUT, DELETE)
     * @param string $uri URI relative de l'API
     * @param array $options Options de la requête (headers, query, json, etc.)
     * @return array Réponse décodée de l'API
     * @throws \RuntimeException En cas d'erreur API
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        $fullUrl = $this->apiBase . $uri;

        // Fusion explicite des headers (pas récursive pour éviter les conflits)
        $userHeaders = $options['headers'] ?? [];
        $options['headers'] = array_merge([
            'Authorization' => 'token ' . $this->apiKey . ':' . $this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $userHeaders);

        // Laisser le client HTTP gérer Content-Length automatiquement
        unset($options['headers']['Content-Length']);

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
    public function getResource(string $doctype, string $name): ?array
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

    public function getActiveEmployees(?string $search = null): array
    {
        $filters = [['status', '=', 'Active']];
        if ($search) {
            $filters[] = ['employee_name', 'like', "%{$search}%"];
        }
        return $this->listResource(
            'Employee',
            $filters,
            ['name','employee_name','company','date_of_joining','date_of_birth','gender','status']
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
        $this->logger->info('getAllSalarySlips called', ['year' => $year]);
        
        $filters = [];
        if ($year) {
            // Essayer plusieurs approches de filtrage par année
            $filters = [
                ['start_date', '>=', "{$year}-01-01"],
                ['start_date', '<=', "{$year}-12-31"],
            ];
        }

        // Récupérer une liste de fiches de paie avec les champs de base
        $this->logger->info('ERPNextService: Fetching basic salary slips with filters', ['filters' => $filters]);
        
        try {
            $basicSalarySlips = $this->listResource('Salary Slip', $filters, [
                'name', 'start_date', 'end_date', 'employee', 'employee_name', 'gross_pay', 'total_deduction', 'net_pay'
            ], 'start_date desc', 10000);
            
            $this->logger->info('ERPNextService: Basic salary slips received', [
                'count' => count($basicSalarySlips), 
                'slips_sample' => array_slice($basicSalarySlips, 0, 2)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('ERPNextService: Failed to fetch basic salary slips', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            // Fallback: essayer sans filtres si l'année est spécifiée
            if ($year) {
                $this->logger->info('ERPNextService: Trying fallback without year filter');
                try {
                    $basicSalarySlips = $this->listResource('Salary Slip', [], [
                        'name', 'start_date', 'end_date', 'employee', 'employee_name', 'gross_pay', 'total_deduction', 'net_pay'
                    ], 'start_date desc', 10000);
                    
                    // Filtrer manuellement par année
                    $basicSalarySlips = array_filter($basicSalarySlips, function($slip) use ($year) {
                        $slipYear = date('Y', strtotime($slip['start_date'] ?? ''));
                        return $slipYear == $year;
                    });
                    
                    $this->logger->info('ERPNextService: Fallback successful, filtered by year', [
                        'count' => count($basicSalarySlips)
                    ]);
                } catch (\Exception $e2) {
                    $this->logger->error('ERPNextService: Fallback also failed', [
                        'error' => $e2->getMessage()
                    ]);
                    return [];
                }
            } else {
                return [];
            }
        }

        $detailedSalarySlips = [];
        $processedCount = 0;
        $maxToProcess = 100; // Limiter pour éviter les timeouts
        
        foreach ($basicSalarySlips as $slip) {
            if ($processedCount >= $maxToProcess) {
                $this->logger->info('ERPNextService: Reached processing limit', [
                    'processed' => $processedCount,
                    'total' => count($basicSalarySlips)
                ]);
                break;
            }
            
            try {
                // Pour chaque fiche de paie, récupérer les détails complets, y compris les tables enfants
                $detailedSlip = $this->getSalarySlipDetails($slip['name']);
                if ($detailedSlip) {
                    $detailedSalarySlips[] = $detailedSlip;
                } else {
                    $this->logger->warning('ERPNextService: Failed to get detailed slip for', ['slip_name' => $slip['name']]);
                    // Utiliser les données de base si les détails ne sont pas disponibles
                    $detailedSalarySlips[] = $slip;
                }
            } catch (\Exception $e) {
                $this->logger->warning('ERPNextService: Exception getting detailed slip', [
                    'slip_name' => $slip['name'],
                    'error' => $e->getMessage()
                ]);
                // Utiliser les données de base en cas d'erreur
                $detailedSalarySlips[] = $slip;
            }
            
            $processedCount++;
        }
        
        $this->logger->info('ERPNextService: Detailed salary slips collected', [
            'count' => count($detailedSalarySlips), 
            'processed' => $processedCount,
            'total_basic' => count($basicSalarySlips)
        ]);
        
        return $detailedSalarySlips;
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
        $this->logger->info('getSalarySlipsByPeriod called', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        // Essayer plusieurs approches de filtrage pour être sûr de récupérer les données
        $approaches = [
            // Approche 1: Filtrer par start_date dans la période
            [
                ['start_date', '>=', $startDate],
                ['start_date', '<=', $endDate]
            ],
            // Approche 2: Filtrer par end_date dans la période  
            [
                ['end_date', '>=', $startDate],
                ['end_date', '<=', $endDate]
            ],
            // Approche 3: Filtrer par chevauchement de période
            [
                ['start_date', '<=', $endDate],
                ['end_date', '>=', $startDate]
            ]
        ];
        
        $allSlips = [];
        $slipNames = []; // Pour éviter les doublons
        
        foreach ($approaches as $index => $filters) {
            try {
                $this->logger->debug("Trying approach " . ($index + 1), ['filters' => $filters]);
                
                $slips = $this->listResource('Salary Slip', $filters, [
                    'name','employee','employee_name','start_date','end_date','gross_pay','total_deduction','net_pay'
                ]);
                
                $this->logger->debug("Approach " . ($index + 1) . " returned " . count($slips) . " slips");
                
                foreach ($slips as $slip) {
                    $slipName = $slip['name'];
                    if (!in_array($slipName, $slipNames)) {
                        $slipNames[] = $slipName;
                        $allSlips[] = $slip;
                    }
                }
                
                // Si on a trouvé des résultats avec la première approche, on peut s'arrêter
                if (!empty($slips) && $index === 0) {
                    break;
                }
                
            } catch (\Exception $e) {
                $this->logger->warning("Approach " . ($index + 1) . " failed", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->logger->info('getSalarySlipsByPeriod result', [
            'total_slips_found' => count($allSlips),
            'unique_slips' => count($slipNames)
        ]);
        
        return $allSlips;
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
                'structure' => $dataWithDoctype['salary_structure'],
                'base_amount' => $dataWithDoctype['base'] ?? 'not specified'
            ]);
            
            // Créer la fiche de paie
            $result = $this->request('POST', '/api/method/frappe.client.insert', [
                'json' => ['doc' => $dataWithDoctype]
            ]);
            
            // Si un montant de base est spécifié, mettre à jour la fiche de paie avec les montants corrects
            if (isset($dataWithDoctype['base']) && is_numeric($dataWithDoctype['base']) && $dataWithDoctype['base'] > 0) {
                $this->updateSalarySlipAmounts($result['name'], (float)$dataWithDoctype['base']);
            }
            
            return $result;
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
        
        $this->logger->info("Updating salary slip in ERPNext", [
            'slip_name' => $data['name'] ?? 'Unknown',
            'gross_pay' => $data['gross_pay'] ?? 'Not set',
            'total_deduction' => $data['total_deduction'] ?? 'Not set',
            'net_pay' => $data['net_pay'] ?? 'Not set',
            'original_docstatus' => $data['docstatus'] ?? 'Not set'
        ]);
        
        // SOLUTION SIMPLE: Toujours forcer le statut à draft pour permettre la modification
        $data['docstatus'] = 0;
        
        // SOLUTION SIMPLE: Sauvegarder directement en draft sans soumission
        // Validation des données avant sauvegarde
        $this->validateSalarySlipData($data);
        
        $result = $this->request('POST', '/api/method/frappe.client.save', [
            'json' => ['doc' => $data]
        ]);
        
        $this->logger->info("Salary slip saved successfully in draft", [
            'slip_name' => $data['name'] ?? 'Unknown',
            'final_docstatus' => 0,
            'gross_pay' => $data['gross_pay'] ?? 'Not set',
            'net_pay' => $data['net_pay'] ?? 'Not set'
        ]);
        
        return $result;
    }

    /**
     * Supprime une fiche de paie existante
     */
    public function deleteSalarySlip(string $salarySlipName): bool
    {
        try {
            $this->logger->info("Attempting to delete salary slip", [
                'salary_slip' => $salarySlipName
            ]);

            // D'abord, récupérer les détails de la fiche pour connaître son statut
            try {
                $slipDetails = $this->request('GET', '/api/resource/Salary Slip/' . urlencode($salarySlipName));
                $docstatus = $slipDetails['data']['docstatus'] ?? 0;
                
                $this->logger->info("Salary slip details retrieved", [
                    'salary_slip' => $salarySlipName,
                    'docstatus' => $docstatus
                ]);

                // Si la fiche est soumise (docstatus = 1), l'annuler d'abord
                if ($docstatus == 1) {
                    try {
                        $this->request('POST', '/api/method/frappe.client.cancel_doc', [
                            'json' => [
                                'doctype' => 'Salary Slip',
                                'name' => $salarySlipName
                            ]
                        ]);
                        $this->logger->info("Salary slip cancelled successfully", [
                            'salary_slip' => $salarySlipName
                        ]);
                        
                        // Attendre un peu pour que l'annulation soit prise en compte
                        sleep(1);
                    } catch (\Exception $e) {
                        $this->logger->warning("Failed to cancel salary slip", [
                            'salary_slip' => $salarySlipName,
                            'error' => $e->getMessage()
                        ]);
                        // Continuer quand même pour essayer la suppression
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Could not retrieve salary slip details", [
                    'salary_slip' => $salarySlipName,
                    'error' => $e->getMessage()
                ]);
                // Continuer quand même
            }

            // Ensuite, supprimer la fiche
            try {
                $this->request('DELETE', '/api/resource/Salary Slip/' . urlencode($salarySlipName));
                $this->logger->info("Salary slip deleted successfully", [
                    'salary_slip' => $salarySlipName
                ]);
                return true;
            } catch (\Exception $e) {
                // Si la suppression directe échoue, essayer avec l'API frappe.client.delete
                $this->logger->info("Direct delete failed, trying frappe.client.delete", [
                    'salary_slip' => $salarySlipName,
                    'error' => $e->getMessage()
                ]);
                
                try {
                    $this->request('POST', '/api/method/frappe.client.delete', [
                        'json' => [
                            'doctype' => 'Salary Slip',
                            'name' => $salarySlipName
                        ]
                    ]);
                    $this->logger->info("Salary slip deleted successfully via frappe.client.delete", [
                        'salary_slip' => $salarySlipName
                    ]);
                    return true;
                } catch (\Exception $e2) {
                    $this->logger->error("All delete methods failed", [
                        'salary_slip' => $salarySlipName,
                        'direct_delete_error' => $e->getMessage(),
                        'client_delete_error' => $e2->getMessage()
                    ]);
                    return false;
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete salary slip", [
                'salary_slip' => $salarySlipName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Supprime toutes les fiches de paie existantes pour un employé sur une période
     */
    public function deleteExistingSalarySlips(string $employeeId, string $startDate, string $endDate): array
    {
        $deletedSlips = [];
        $errors = [];

        try {
            // Récupérer les fiches existantes pour cette période
            $existingSlips = $this->getSalarySlips([
                'employee' => $employeeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $this->logger->info("Found existing salary slips to delete", [
                'employee' => $employeeId,
                'period' => "$startDate to $endDate",
                'slips_count' => count($existingSlips),
                'slips' => array_column($existingSlips, 'name')
            ]);

            foreach ($existingSlips as $slip) {
                $slipName = $slip['name'];
                
                // Essayer plusieurs méthodes de suppression
                $deleted = false;
                
                // Méthode 1: Suppression directe
                if ($this->deleteSalarySlip($slipName)) {
                    $deletedSlips[] = $slipName;
                    $deleted = true;
                } else {
                    // Méthode 2: Essayer d'annuler puis supprimer avec une approche différente
                    try {
                        $this->logger->info("Trying alternative deletion method", [
                            'salary_slip' => $slipName
                        ]);
                        
                        // Récupérer les détails de la fiche
                        $slipDetails = $this->request('GET', '/api/resource/Salary Slip/' . urlencode($slipName));
                        
                        // Si la fiche est soumise, l'annuler
                        if (isset($slipDetails['data']['docstatus']) && $slipDetails['data']['docstatus'] == 1) {
                            $this->request('POST', '/api/method/frappe.client.cancel_doc', [
                                'json' => [
                                    'doctype' => 'Salary Slip',
                                    'name' => $slipName
                                ]
                            ]);
                            sleep(1); // Attendre que l'annulation soit prise en compte
                        }
                        
                        // Essayer de supprimer avec l'API frappe.desk.form.utils.delete_doc
                        $this->request('POST', '/api/method/frappe.desk.form.utils.delete_doc', [
                            'json' => [
                                'doctype' => 'Salary Slip',
                                'name' => $slipName
                            ]
                        ]);
                        
                        $deletedSlips[] = $slipName;
                        $deleted = true;
                        
                        $this->logger->info("Alternative deletion method succeeded", [
                            'salary_slip' => $slipName
                        ]);
                        
                    } catch (\Exception $e) {
                        $this->logger->warning("Alternative deletion method failed", [
                            'salary_slip' => $slipName,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                if (!$deleted) {
                    $errors[] = "Failed to delete salary slip: $slipName";
                    $this->logger->error("All deletion methods failed", [
                        'salary_slip' => $slipName
                    ]);
                }
            }

            $this->logger->info("Completed deletion of existing salary slips", [
                'employee' => $employeeId,
                'period' => "$startDate to $endDate",
                'deleted_count' => count($deletedSlips),
                'error_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            $errors[] = "Error retrieving existing salary slips: " . $e->getMessage();
            $this->logger->error("Error in deleteExistingSalarySlips", [
                'employee' => $employeeId,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'deleted' => $deletedSlips,
            'errors' => $errors
        ];
    }

    /**
     * Met à jour les montants dans une fiche de paie avec le montant de base spécifié
     */
    public function updateSalarySlipAmounts(string $salarySlipName, float $baseAmount): array
    {
        try {
            // Récupérer la fiche de paie complète
            $salarySlip = $this->getResource('Salary Slip', $salarySlipName);
            
            if (!$salarySlip) {
                throw new \RuntimeException("Salary slip not found: $salarySlipName");
            }
            
            $this->logger->info("Updating salary slip amounts", [
                'slip' => $salarySlipName,
                'base_amount' => $baseAmount,
                'employee' => $salarySlip['employee'] ?? 'unknown'
            ]);
            
            // Mettre à jour les montants des composants earnings
            $totalEarnings = 0;
            $hasEarnings = false;
            
            if (isset($salarySlip['earnings']) && is_array($salarySlip['earnings']) && count($salarySlip['earnings']) > 0) {
                $hasEarnings = true;
                foreach ($salarySlip['earnings'] as &$earning) {
                    if (isset($earning['salary_component'])) {
                        $component = $earning['salary_component'];
                        
                        // Calculer le montant selon le composant
                        if ($component === 'Salaire Base' || (isset($earning['abbr']) && $earning['abbr'] === 'SB')) {
                            $earning['amount'] = $baseAmount;
                            $totalEarnings += $baseAmount;
                        } elseif ($component === 'Indemnité' || (isset($earning['abbr']) && $earning['abbr'] === 'IND')) {
                            $indemnityAmount = $baseAmount * 0.3;
                            $earning['amount'] = $indemnityAmount;
                            $totalEarnings += $indemnityAmount;
                        }
                        
                        $this->logger->debug("Updated earning component", [
                            'component' => $component,
                            'amount' => $earning['amount']
                        ]);
                    }
                }
            }
            
            // Si pas de composants earnings, créer les composants de base
            if (!$hasEarnings) {
                $this->logger->info("No earnings components found, creating default components");
                
                $salarySlip['earnings'] = [
                    [
                        'salary_component' => 'Salaire Base',
                        'abbr' => 'SB',
                        'amount' => $baseAmount,
                        'default_amount' => $baseAmount,
                        'depends_on_payment_days' => 1,
                        'is_tax_applicable' => 1
                    ],
                    [
                        'salary_component' => 'Indemnité',
                        'abbr' => 'IND',
                        'amount' => $baseAmount * 0.3,
                        'default_amount' => 0,
                        'depends_on_payment_days' => 0,
                        'is_tax_applicable' => 1,
                        'amount_based_on_formula' => 1,
                        'formula' => 'SB * 0.3'
                    ]
                ];
                
                $totalEarnings = $baseAmount + ($baseAmount * 0.3);
            }
            
            // Mettre à jour les montants des composants deductions
            $totalDeductions = 0;
            $hasDeductions = false;
            
            if (isset($salarySlip['deductions']) && is_array($salarySlip['deductions']) && count($salarySlip['deductions']) > 0) {
                $hasDeductions = true;
                foreach ($salarySlip['deductions'] as &$deduction) {
                    if (isset($deduction['salary_component'])) {
                        $component = $deduction['salary_component'];
                        
                        // Calculer le montant selon le composant
                        if ($component === 'Taxe sociale' || (isset($deduction['abbr']) && $deduction['abbr'] === 'TS')) {
                            $taxAmount = ($baseAmount + ($baseAmount * 0.3)) * 0.2;
                            $deduction['amount'] = $taxAmount;
                            $totalDeductions += $taxAmount;
                        }
                        
                        $this->logger->debug("Updated deduction component", [
                            'component' => $component,
                            'amount' => $deduction['amount']
                        ]);
                    }
                }
            }
            
            // Si pas de composants deductions, créer les composants de base
            if (!$hasDeductions) {
                $this->logger->info("No deduction components found, creating default components");
                
                $taxAmount = ($baseAmount + ($baseAmount * 0.3)) * 0.2;
                $salarySlip['deductions'] = [
                    [
                        'salary_component' => 'Taxe sociale',
                        'abbr' => 'TS',
                        'amount' => $taxAmount,
                        'default_amount' => 0,
                        'depends_on_payment_days' => 0,
                        'is_tax_applicable' => 1,
                        'amount_based_on_formula' => 1,
                        'formula' => '(SB + IND) * 0.2'
                    ]
                ];
                
                $totalDeductions = $taxAmount;
            }
            
            // Mettre à jour les totaux
            $salarySlip['total_earning'] = $totalEarnings;
            $salarySlip['total_deduction'] = $totalDeductions;
            $salarySlip['net_pay'] = $totalEarnings - $totalDeductions;
            $salarySlip['gross_pay'] = $totalEarnings;
            
            $this->logger->info("Updated salary slip totals", [
                'slip' => $salarySlipName,
                'total_earning' => $totalEarnings,
                'total_deduction' => $totalDeductions,
                'net_pay' => $salarySlip['net_pay']
            ]);
            
            // Sauvegarder la fiche de paie mise à jour
            $result = $this->request('POST', '/api/method/frappe.client.save', [
                'json' => ['doc' => $salarySlip]
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            $this->logger->error("Error updating salary slip amounts", [
                'slip' => $salarySlipName,
                'base_amount' => $baseAmount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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

    /**
     * Met à jour le montant de base d'une assignation de structure salariale
     */
    public function updateSalaryStructureAssignmentBase(string $employeeId, string $salaryStructureName, string $fromDate, float $baseAmount): array
    {
        try {
            // Trouver l'assignation existante
            $assignment = $this->getEmployeeSalaryStructureAssignment($employeeId, $fromDate);
            
            if (!$assignment) {
                throw new \RuntimeException("No salary structure assignment found for employee $employeeId on date $fromDate");
            }
            
            // Récupérer le document complet
            $fullAssignment = $this->getResource('Salary Structure Assignment', $assignment['name']);
            
            if (!$fullAssignment) {
                throw new \RuntimeException("Failed to retrieve full assignment document: {$assignment['name']}");
            }
            
            // Vérifier si le montant de base est déjà correct
            if (isset($fullAssignment['base']) && abs((float)$fullAssignment['base'] - $baseAmount) < 0.01) {
                $this->logger->info("Base amount already correct", [
                    'assignment' => $assignment['name'],
                    'current_base' => $fullAssignment['base'],
                    'requested_base' => $baseAmount
                ]);
                return $fullAssignment;
            }
            
            // Vérifier si le document est déjà soumis
            if (isset($fullAssignment['docstatus']) && $fullAssignment['docstatus'] == 1) {
                $this->logger->warning("Cannot update base amount - assignment already submitted", [
                    'assignment' => $assignment['name'],
                    'employee' => $employeeId,
                    'current_base' => $fullAssignment['base'] ?? 0,
                    'requested_base' => $baseAmount
                ]);
                
                // Retourner l'assignation existante sans modification
                return $fullAssignment;
            }
            
            $oldBase = $fullAssignment['base'] ?? 0;
            
            // Mettre à jour le montant de base
            $fullAssignment['base'] = $baseAmount;
            
            $this->logger->info("Updating salary structure assignment base amount", [
                'assignment' => $assignment['name'],
                'employee' => $employeeId,
                'old_base' => $oldBase,
                'new_base' => $baseAmount
            ]);
            
            // Sauvegarder le document mis à jour
            $result = $this->request('POST', '/api/method/frappe.client.save', [
                'json' => ['doc' => $fullAssignment]
            ]);
            
            return $result;
            
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            
            // Si c'est une erreur de modification après soumission, on l'ignore
            if (str_contains($msg, 'UpdateAfterSubmitError') || str_contains($msg, 'after submission')) {
                $this->logger->warning("Cannot update assignment base - already submitted", [
                    'employee' => $employeeId,
                    'structure' => $salaryStructureName,
                    'from_date' => $fromDate,
                    'base_amount' => $baseAmount,
                    'error' => $msg
                ]);
                
                // Retourner l'assignation existante
                $assignment = $this->getEmployeeSalaryStructureAssignment($employeeId, $fromDate);
                return $this->getResource('Salary Structure Assignment', $assignment['name']);
            }
            
            $this->logger->error("Error updating salary structure assignment base", [
                'employee' => $employeeId,
                'structure' => $salaryStructureName,
                'from_date' => $fromDate,
                'base_amount' => $baseAmount,
                'error' => $msg
            ]);
            throw $e;
        }
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

    /**
     * Generic method to submit any document type
     */
    public function submitDocument(string $doctype, string $name): array
    {
        $maxRetries = 3;
        $retryCount = 0;
        $lastException = null;

        while ($retryCount < $maxRetries) {
            try {
                if ($retryCount > 0) {
                    $sleepTime = $retryCount * 2;
                    $this->logger->info("Retrying submitDocument after delay", [
                        'doctype' => $doctype,
                        'name' => $name,
                        'retry' => $retryCount,
                        'sleep' => $sleepTime
                    ]);
                    sleep($sleepTime);
                }

                $docToSubmit = ['doctype' => $doctype, 'name' => $name];
                
                // Si c'est une re-tentative après TimestampMismatchError, on utilise le document rafraîchi
                if ($retryCount > 0 && $lastException && str_contains($lastException->getMessage(), 'TimestampMismatchError')) {
                    $latestDoc = $this->getResource($doctype, $name);
                    if (!$latestDoc) {
                        $this->logger->error("Failed to refresh document for submission during retry", [
                            'doctype' => $doctype,
                            'name' => $name
                        ]);
                        throw new \RuntimeException("Failed to refresh document for submission: $doctype/$name");
                    }
                    $docToSubmit = array_merge($docToSubmit, $latestDoc);
                    $this->logger->info("Attempting submit with refreshed document", [
                        'doctype' => $doctype,
                        'name' => $name,
                        'doc' => $docToSubmit
                    ]);
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
                    $this->logger->info("Document already submitted", [
                        'doctype' => $doctype,
                        'name' => $name
                    ]);
                    return ['name' => $name, 'already_submitted' => true];
                }

                if (str_contains($msg, 'TimestampMismatchError')) {
                    $this->logger->warning("TimestampMismatchError in submitDocument, retrying", [
                        'doctype' => $doctype,
                        'name' => $name,
                        'retry' => $retryCount,
                        'error' => $msg
                    ]);
                    // Ne pas lancer d'exception ici, laisser la boucle retenter
                } else {
                    $this->logger->error("Error in submitDocument", [
                        'doctype' => $doctype,
                        'name' => $name,
                        'error' => $msg
                    ]);
                    throw $e; // Pour les autres types d'erreurs, on arrête immédiatement
                }
            }
            $retryCount++;
        }

        $this->logger->error("Max retries exceeded in submitDocument", [
            'doctype' => $doctype,
            'name' => $name,
            'retries' => $maxRetries
        ]);

        throw $lastException ?? new \RuntimeException("Failed to submit document after $maxRetries retries: $doctype/$name");
    }

    /**
     * Submit a salary slip with retry logic
     */
    public function submitSalarySlip(string $name): array
    {
        $maxRetries = 3;
        $retryCount = 0;
        $lastException = null;

        while ($retryCount < $maxRetries) {
            try {
                if ($retryCount > 0) {
                    $sleepTime = $retryCount * 2;
                    $this->logger->info("Retrying submitSalarySlip after delay", [
                        'name' => $name,
                        'retry' => $retryCount,
                        'sleep' => $sleepTime
                    ]);
                    sleep($sleepTime);
                }

                // Récupérer le document complet pour préserver les totaux calculés
                $latestDoc = $this->getResource('Salary Slip', $name);
                if (!$latestDoc) {
                    $this->logger->error("Failed to get salary slip for submission", ['name' => $name]);
                    throw new \RuntimeException("Failed to get salary slip for submission: $name");
                }
                
                // S'assurer que les totaux sont corrects avant soumission
                $this->ensureSalarySlipTotals($latestDoc);
                
                $docToSubmit = array_merge(['doctype' => 'Salary Slip', 'name' => $name], $latestDoc);
                
                $this->logger->info("Submitting salary slip with preserved totals", [
                    'name' => $name, 
                    'gross_pay' => $latestDoc['gross_pay'] ?? 'not set',
                    'total_deduction' => $latestDoc['total_deduction'] ?? 'not set',
                    'net_pay' => $latestDoc['net_pay'] ?? 'not set'
                ]);

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
                    $this->logger->info("Salary slip already submitted", ['name' => $name]);
                    return ['name' => $name, 'already_submitted' => true];
                }

                if (str_contains($msg, 'TimestampMismatchError')) {
                    $this->logger->warning("TimestampMismatchError in submitSalarySlip, retrying", [
                        'name' => $name,
                        'retry' => $retryCount,
                        'error' => $msg
                    ]);
                    // Ne pas lancer d'exception ici, laisser la boucle retenter
                } else {
                    $this->logger->error("Error in submitSalarySlip", [
                        'name' => $name,
                        'error' => $msg
                    ]);
                    throw $e; // Pour les autres types d'erreurs, on arrête immédiatement
                }
            }
            $retryCount++;
        }

        $this->logger->error("Max retries exceeded in submitSalarySlip", [
            'name' => $name,
            'retries' => $maxRetries
        ]);

        throw $lastException ?? new \RuntimeException("Failed to submit salary slip after $maxRetries retries");
    }

    /**
     * Valide les données d'une fiche de paie avant sauvegarde
     */
    private function validateSalarySlipData(array &$data): void
    {
        // S'assurer que les champs obligatoires sont présents
        $requiredFields = ['name', 'employee', 'start_date', 'end_date'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '$field' is missing or empty");
            }
        }
        
        // Valider les montants
        $numericFields = ['gross_pay', 'total_deduction', 'net_pay'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                $this->logger->warning("Non-numeric value found for $field, converting", [
                    'field' => $field,
                    'value' => $data[$field]
                ]);
                $data[$field] = (float)$data[$field];
            }
        }
        
        // S'assurer que les totaux sont cohérents
        $calculatedEarnings = 0;
        $calculatedDeductions = 0;
        
        if (isset($data['earnings']) && is_array($data['earnings'])) {
            foreach ($data['earnings'] as $earning) {
                if (isset($earning['amount']) && is_numeric($earning['amount'])) {
                    $calculatedEarnings += (float)$earning['amount'];
                }
            }
        }
        
        if (isset($data['deductions']) && is_array($data['deductions'])) {
            foreach ($data['deductions'] as $deduction) {
                if (isset($deduction['amount']) && is_numeric($deduction['amount'])) {
                    $calculatedDeductions += (float)$deduction['amount'];
                }
            }
        }
        
        // Corriger les totaux si nécessaire
        $tolerance = 0.01;
        if (abs(($data['gross_pay'] ?? 0) - $calculatedEarnings) > $tolerance) {
            $this->logger->info("Correcting gross_pay to match earnings total", [
                'slip' => $data['name'],
                'old_gross_pay' => $data['gross_pay'] ?? 0,
                'new_gross_pay' => $calculatedEarnings
            ]);
            $data['gross_pay'] = $calculatedEarnings;
        }
        
        if (abs(($data['total_deduction'] ?? 0) - $calculatedDeductions) > $tolerance) {
            $this->logger->info("Correcting total_deduction to match deductions total", [
                'slip' => $data['name'],
                'old_total_deduction' => $data['total_deduction'] ?? 0,
                'new_total_deduction' => $calculatedDeductions
            ]);
            $data['total_deduction'] = $calculatedDeductions;
        }
        
        $calculatedNetPay = $calculatedEarnings - $calculatedDeductions;
        if (abs(($data['net_pay'] ?? 0) - $calculatedNetPay) > $tolerance) {
            $this->logger->info("Correcting net_pay to match calculated value", [
                'slip' => $data['name'],
                'old_net_pay' => $data['net_pay'] ?? 0,
                'new_net_pay' => $calculatedNetPay
            ]);
            $data['net_pay'] = $calculatedNetPay;
        }
        
        // S'assurer que les champs de base sont cohérents
        $data['base_gross_pay'] = $data['gross_pay'];
        $data['base_total_deduction'] = $data['total_deduction'];
        $data['base_net_pay'] = $data['net_pay'];
        $data['rounded_total'] = $data['net_pay'];
        $data['base_rounded_total'] = $data['net_pay'];
        
        $this->logger->debug("Salary slip data validated", [
            'slip' => $data['name'],
            'gross_pay' => $data['gross_pay'],
            'total_deduction' => $data['total_deduction'],
            'net_pay' => $data['net_pay']
        ]);
    }

    /**
     * Ensure salary slip totals are correctly calculated
     */
    private function ensureSalarySlipTotals(array &$salarySlip): void
    {
        $totalEarnings = 0;
        $totalDeductions = 0;
        
        // Calculer le total des earnings
        if (isset($salarySlip['earnings']) && is_array($salarySlip['earnings'])) {
            foreach ($salarySlip['earnings'] as $earning) {
                if (isset($earning['amount']) && is_numeric($earning['amount'])) {
                    $totalEarnings += (float)$earning['amount'];
                }
            }
        }
        
        // Calculer le total des deductions
        if (isset($salarySlip['deductions']) && is_array($salarySlip['deductions'])) {
            foreach ($salarySlip['deductions'] as $deduction) {
                if (isset($deduction['amount']) && is_numeric($deduction['amount'])) {
                    $totalDeductions += (float)$deduction['amount'];
                }
            }
        }
        
        // Mettre à jour les totaux
        $salarySlip['total_earning'] = $totalEarnings;
        $salarySlip['gross_pay'] = $totalEarnings;
        $salarySlip['total_deduction'] = $totalDeductions;
        $salarySlip['net_pay'] = $totalEarnings - $totalDeductions;
        
        // S'assurer que les champs de base sont présents
        $salarySlip['rounded_total'] = $salarySlip['net_pay'];
        $salarySlip['total_in_words'] = $this->numberToWords($salarySlip['net_pay']);
        
        $this->logger->debug("Ensured salary slip totals", [
            'name' => $salarySlip['name'] ?? 'unknown',
            'total_earning' => $totalEarnings,
            'total_deduction' => $totalDeductions,
            'net_pay' => $salarySlip['net_pay']
        ]);
    }

    /**
     * Convert number to words (basic implementation)
     */
    private function numberToWords(float $amount): string
    {
        // Simple implementation - can be enhanced later
        return number_format($amount, 2) . ' Ariary';
    }

    /**
     * Generic method to insert a document
     */
    public function insertDocument(array $docData): array
    {
        return $this->request('POST', '/api/method/frappe.client.insert', [
            'json' => ['doc' => $docData]
        ]);
    }

    /**
     * Generic method to set a field value on a document
     */
    public function setDocumentValue(string $doctype, string $name, string $fieldname, $value): array
    {
        return $this->request('POST', '/api/method/frappe.client.set_value', [
            'json' => [
                'doctype' => $doctype,
                'name' => $name,
                'fieldname' => $fieldname,
                'value' => $value
            ]
        ]);
    }

    /**
     * Submit multiple documents in batch
     */
    public function submitMultipleDocuments(array $docs): array
    {
        return $this->request('POST', '/api/method/frappe.desk.form.save.submit_multiple_docs', [
            'json' => ['docs' => $docs]
        ]);
    }
}
