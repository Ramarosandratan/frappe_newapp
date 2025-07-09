## High-Level Documentation: Salary Slip Import Command

### Overview

This Symfony console command, `app:import-salary-slips`, is designed to import employee salary information from three CSV files into an ERPNext system. It orchestrates the process of importing employees, salary structures, and salary slips, ensuring data integrity and logging progress and errors throughout.

---

### Command Arguments

- **employees_file**: Path to CSV with employee details.
- **structures_file**: Path to CSV with salary structures and their components.
- **data_file**: Path to CSV with salary slip information (salaries per employee per month).

---

### Execution Flow

1. **Import Employees**  
   - Reads the employees CSV.
   - Validates required fields.
   - Ensures referenced company exists (creates in ERPNext if missing).
   - Imports each employee; on success, maps their reference for further processing.

2. **Import Salary Structures**  
   - Reads the salary structures CSV.
   - Validates required fields.
   - Ensures referenced company exists.
   - For each structure:
     - Imports salary components (earnings and deductions).
     - Imports the salary structure itself, associating it with its components.

3. **Import Salary Slips**  
   - Reads the salary data CSV.
   - Validates required fields.
   - For each record:
     - Resolves employee reference and salary structure.
     - Converts month to appropriate date range.
     - Imports the salary slip for the employee in ERPNext.

---

### Key Features

- **CSV Parsing**: Uses League\Csv for consistent reading.
- **Validation**: Ensures presence of required fields before processing.
- **Error Handling**: Logs and reports all failures per record, but continues processing others.
- **Data Mapping**: Maintains a mapping from employee references to ERPNext identifiers.
- **Company Management**: Ensures any company mentioned exists in ERPNext; creates if not present.
- **Field Conversion**: Handles conversion of dates and salary amounts to required formats.
- **Progress Reporting**: Uses SymfonyStyle for user-friendly CLI feedback on progress, success, and failure.

---

### Services Used

- **ErpNextService**
  - Handles actual operations against the ERPNext API for:
    - Employee creation
    - Salary component creation
    - Salary structure creation
    - Salary slip creation
    - Company existence/query/creation

- **LoggerInterface**
  - Captures all errors and key actions for traceability.

---

### Helper Methods

- **importEmployees**: Imports users and ensures employee-company relation.
- **importSalaryStructures**: Organizes input by structure, then creates all associated components/structures.
- **importSalaryData**: Adds salary slips after mapping employee references and structure names.
- **ensureCompanyExists**: Checks if a company exists in ERPNext, creates if necessary.
- **convertDate**: Transforms dates from "DD/MM/YYYY" to "YYYY-MM-DD".
- **parseSalaryAmount**: Normalizes salary amounts to float, handling region-specific formats.

---

### Error Management

- Each import step catches and logs exceptions on a per-record basis.
- The overall import halts and reports if a critical error is encountered (e.g., missing required fields).

---

### Use Case

This command is useful for bulk initial data loading or regular synchronization of employee and payroll information from local HR/payroll systems into the ERPNext platform using CSV extracts.

---

### Typical Usage

```sh
php bin/console app:import-salary-slips employees.csv structures.csv salaries.csv
```
The command logs details and errors to the console and the configured logger, ensuring transparency during the import process.