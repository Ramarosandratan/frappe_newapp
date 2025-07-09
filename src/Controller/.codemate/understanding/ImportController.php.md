# ImportController – High-Level Documentation

## Overview

The `ImportController` is a Symfony controller designed for multi-step bulk imports of employee, salary structure, and payroll data from CSV files into an ERP system (notably ERPNext). It encapsulates a robust workflow for deriving structured business data from potentially unstructured CSV inputs, ensuring data integrity, cross-entity relationships, and seamless integration with the back-end services.

## Key Features

- **Multi-CSV Import Workflow:** Handles three CSV files in tandem—employees, salary structures, and salary data—allowing for streamlined mass data onboarding into ERPNext.
- **Input Validation:** Each CSV file is validated for required fields, schema consistency, and content integrity before processing, ensuring errors are caught early.
- **Idempotency & Resilience:** Prevents duplicate records by verifying existence before creation and gracefully handles recoverable errors or partial failures with robust logging and user feedback.
- **Cross-Entity Linking:** Automatically manages relationships between employees, companies, salary structures, and holiday lists, setting defaults where necessary.
- **ERP Service Integration:** Delegates all data persistence and lookup operations to the injected `ErpNextService`, keeping controller logic clean and focused on orchestration.
- **Comprehensive Logging & Notifications:** Supports detailed logging at multiple stages for troubleshooting, and uses flash messages to inform users of import results and issues.

## Main Processes

### 1. Import Entry Point (`index` action)

- Presents and processes a form for the upload of three CSV files.
- On form submission and validation:
  1. Processes employees.
  2. Processes salary structures.
  3. Processes salary data.
- Uses try/catch with logging for overarching error reporting and user notification.

### 2. Employee Import (`importEmployees`)

- Validates header and required fields.
- For each employee:
  - Checks/creates company, tracks companies for later holiday list assignment.
  - Checks for pre-existing employees (by employee number).
    - Associates the relevant holiday list if already exists.
  - Parses fields, checks/creates or updates holiday lists.
  - Adds the employee via `ErpNextService`.
  - Associates the employee with a holiday list.
- At the end, ensures every imported company has an associated default holiday list.
- Returns a mapping of employee references to their unique IDs (for use in salary data import).

### 3. Salary Structure Import (`importSalaryStructures`)

- Validates header and required fields.
- Aggregates salary components by salary structure.
- For each structure:
  - Ensures each company exists.
  - Creates/updates salary components.
  - Builds the structure by distributing components into earnings and deductions.
  - Saves the salary structure via `ErpNextService`.
  - (Optionally) Assigns the structure to all mapped employees.
- Provides flash messages for success/failure per structure.

### 4. Salary Data Import (`importSalaryData`)

- Validates header and required fields.
- For each payroll record:
  - Finds or re-fetches employee (by reference).
  - Checks for referenced salary structure.
  - Parses pay period and amounts, handles payroll date formats.
  - Ensures the employee has the salary structure assigned (with a lead time for payroll coverage).
  - Adds the salary slip via `ErpNextService`.
- Accumulates and reports results/errors via flash messages and logs.

### 5. Supporting Utilities

- **Date Conversion (`convertDate`)**: Robust date parsing, expecting "DD/MM/YYYY" format, throws on misformatting.
- **Company Management (`ensureCompanyExists`)**: Checks/creates companies, derives abbreviations, and waits for backend acceptance.
- **Salary Parsing (`parseSalaryAmount`)**: Handles French monetary formatting (spaces, commas) and ensures numerical integrity.
- **Safe Formula Evaluation (`safeEval`)**: Uses a math parser to safely compute formulas, never using `eval()`; ensures only permitted operations and numeric results.

## Dependencies

- **Form Type:** `MultiCsvImportType` (for upload UI)
- **ERP Service:** `ErpNextService` (handles all ERPNext API communications)
- **CSV Parsing:** [league/csv](https://csv.thephpleague.com/)
- **Logging:** PSR-3 Logger
- **Math Parser:** Expects `MathParser\StdMathParser` for safe arithmetic evaluations
- **Symfony Components:** Routing, Request/Response handling, Templating, Flash messages, etc.

## Error Handling

- All major steps are surrounded by try/catch blocks.
- User-facing errors are surfaced via flash messages.
- Technical errors (including stack traces) are sent to the logger for diagnosis.

## Use Cases

- **HR & Payroll Data Migration:** For organizations onboarding to ERPNext, this controller can be adapted to rapidly import key HR and payroll records from flat files.
- **Maintenance or Synchronization Jobs:** Useful for scheduled updates where HR, structure, or payroll data exports from other HR systems are funneled into ERPNext.
- **Admin UI for HR Staff:** Coupled with form templates, enables non-technical users to upload and import organizational HR data.

---

**Note:** The controller expects CSV files to follow strict field naming/company naming conventions as enforced by the code's validations. Any deviation will result in user-facing error messages and abort processing for the affected records.