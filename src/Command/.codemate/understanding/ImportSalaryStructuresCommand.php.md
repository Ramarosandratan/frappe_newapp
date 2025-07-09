# High-Level Documentation: ImportSalaryStructuresCommand

## Overview

This Symfony console command (`app:import-salary-structures`) is designed to import salary structures and their associated components (earnings and deductions) from a CSV file into an ERPNext system.

---

## Purpose

- **Reads**: A CSV file containing salary structure data and components.
- **Validates**: Ensures required fields are present and not empty for each row.
- **Ensures Companies Exist**: Checks if specified companies exist in ERPNext, creates them if not.
- **Creates/Updates**:
    - **Salary Components**: Each component for the structure (earnings/deductions).
    - **Salary Structures**: Associates components as earnings or deductions and saves the salary structure.

---

## Flow Summary

1. **Command Setup**
   - Requires a file path to a CSV as its only argument.

2. **CSV Parsing and Validation**
   - Loads the CSV file using `league/csv`.
   - Verifies that mandatory headers (`salary structure, name, Abbr, type, valeur, company`) are present.
   - Ensures all required fields are filled in for each CSV row.

3. **Company Existence**
   - For each row, checks if the company exists in ERPNext.
   - Creates the company in ERPNext if not present.

4. **Data Structuring**
   - Groups components by salary structure name, gathering all their associated components and company info.

5. **ERPNext Import**
   - For each salary structure:
     - Iterates over its components, creating or updating them in ERPNext (using `ErpNextService->saveSalaryComponent`).
     - Groups components into `earnings` or `deductions`.
     - Creates/updates the salary structure itself (via `ErpNextService->saveSalaryStructure`).

6. **Logging & Feedback**
   - Logs all errors and warnings using `LoggerInterface`.
   - Outputs progress and issues to the console with `SymfonyStyle`.

7. **Exception Handling**
   - Any error aborts the process with proper logging and a failure status.

---

## Key Functional Elements

- **Required CSV Fields**: `salary structure`, `name`, `Abbr`, `type`, `valeur`, `company`.
- **Company Handling**: Auto-creates companies in ERPNext if missing, using an abbreviation derived from the company name.
- **Salary Components**: Differentiates between formula-based and base value components.
- **Separation of Earnings/Deductions**: Uses the `type` field for categorization.
- **Service Layer**: Relies on an injected `ErpNextService` abstraction for all ERPNext REST operations.

---

## Usage

```bash
php bin/console app:import-salary-structures /path/to/salary_structures.csv
```

---

## Error Handling

- Fails gracefully with logging if:
  - The file or required columns are missing.
  - Required field values are missing in any row.
  - Company/component/structure creation fails in ERPNext.
- Continues processing other entities if some components creation fails, but aborts structure creation on critical errors.

---

## Extension Points

- **ERP Integration**: Can be extended to handle more fields with minor code changes.
- **Validation**: Add more complex business rules in field validation.
- **Feedback**: Adapt logging or add notifications/integrations as needed.

---

## Dependencies

- `league/csv` for CSV parsing.
- `Symfony\Component\Console`, `SymfonyStyle` for CLI interaction.
- A custom `ErpNextService` for ERPNext communication.
- PSR logger for error and event logging.

---

**Summary**:  
This command is a robust, automated bridge between company payroll CSV exports and ERPNext's salary structure management, ensuring data consistency, error tracking, and minimum manual intervention.