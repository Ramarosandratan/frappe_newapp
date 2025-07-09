# Code Review Report

## General Observations

- The code is generally well-structured and uses dependency injection, exception handling, logging, and input validation.
- However, there are some design, efficiency, and correctness issues by industry standards.
- Several error handling, optimization, robustness, and clarifications are needed.

---

## 1. **Repeated File Parsing/Open Errors Not Handled**
- When reading CSVs (`Reader::createFromPath`), there is no catch for file not found or not readable, which could cause fatal errors.

### **Suggested Correction**
```php
try {
    $csv = Reader::createFromPath($filePath, 'r');
} catch (\Throwable $e) {
    throw new \RuntimeException("Cannot open file: $filePath");
}
```

---

## 2. **Sleep Usage in ensureCompanyExists**
- `sleep(2);` is used after company creation. This blocks the process and is not an optimal or reliable way to ensure availability.
- In production, this should be replaced by specific recheck or retry with backoff and max attempts.

### **Suggested Correction**
```php
$attempts = 0;
while (!$this->erpNextService->getCompany($companyName) && $attempts < 5) {
    usleep(500000); // sleep for 0.5 seconds
    $attempts++;
}
if (!$this->erpNextService->getCompany($companyName)) {
    throw new \RuntimeException("Company '$companyName' not available after creation.");
}
```

---

## 3. **Improper Gender Mapping**
- In `importEmployees`, gender is mapped as `'Male'` or `'Female'` based only on `'Masculin'`, which may miss other or mistyped values.

### **Suggested Correction**
```php
switch (strtolower(trim($record['genre']))) {
    case 'masculin':
        $gender = 'Male';
        break;
    case 'féminin':
    case 'feminin':
        $gender = 'Female';
        break;
    default:
        $gender = 'Other';
}
'gender' => $gender,
```

---

## 4. **Array Key Existence Not Checked Before Access**
- When mapping employee refs (`$employeeMap[$record['Ref']] = ...`), no check is performed if `employeeMap` already contains the key (could duplicate and overwrite).

### **Suggested Correction**
```php
if (isset($employeeMap[$record['Ref']])) {
    $io->warning("Employee Ref '{$record['Ref']}' already exists. Skipping duplicate.");
    continue;
}
```

---

## 5. **Unvalidated Components in Salary Structure**
- In `importSalaryStructures`, typing for 'type' field assumes only 'earning' or otherwise (deduction), which could slip in invalid types.

### **Suggested Correction**
```php
$type = strtolower($component['type']);
if (!in_array($type, ['earning', 'deduction'])) {
    $io->warning("Invalid type '{$component['type']}' for component '{$component['name']}'. Skipping.");
    continue;
}
```

---

## 6. **DateTime Creation Without Error Check**
- In `importSalaryData`, `$startDate` is created from a format, but no check if parsing succeeded.
- If input is invalid, code throws fatal error in later calls.

### **Suggested Correction**
```php
$startDate = \DateTime::createFromFormat('d/m/Y', $record['Mois']);
if (!$startDate) {
    throw new \InvalidArgumentException("Invalid date format: {$record['Mois']}");
}
```

---

## 7. **parseSalaryAmount: Locale-Unsafe Replacement**
- Replacing both spaces and commas with a dot can result in unintended decimals in locales that use ',' as decimal separator and '.' as thousand separator.

### **Suggested Correction**
```php
$cleaned = str_replace([' ', "\u{00A0}"], '', $amount); // Remove spaces and non-breaking spaces
$cleaned = str_replace(',', '.', $cleaned); // Replace comma with dot (if comma is decimal in locale)
if (!is_numeric($cleaned)) ...
```
*Also, ensure handling only if there is at most one dot in the result.*

---

## 8. **Class method visibility**
- All class methods are `private`, which is fine unless needed for extension; if planned for inheritance, use `protected`.

---

## 9. **Danger of Partial Imports**
- If one record fails, the loop continues, but client may want summary (e.g., number of errors). Currently only successes are counted.

### **Suggested Correction**
```php
$failCount = 0;
foreach (...) {
    try {
        ...
    } catch (...) {
        $failCount++;
        ...
    }
}
if ($failCount > 0) {
    $io->warning("$failCount records failed to import.");
}
```

---

## 10. **Logging Exceptions**
- Log exception objects to get stack traces, not only messages.

### **Suggested Correction**
```php
$this->logger->error('...', ['exception' => $e]);
```

---

## 11. **Returning Early on Non-Critical Error**
- In some methods, fatal errors (e.g., company creation fails) throw exceptions, but other non-critical data issues merely warn. Make sure the strategy is properly discussed for the business requirement (all-or-nothing vs best-effort).

---

## 12. **Code Readability/Formatting**
- Use consistent spacing and line breaks for better readability.
- Avoid deep nesting where possible.

---

## 13. **Strict Type Declarations**
- Add `declare(strict_types=1);` at the top of the PHP file to enforce strict types.

### **Suggested Correction**
```php
declare(strict_types=1);
```

---

## **Summary Table**

| Issue | Place In Code | Industry Standard Correction |
|-------|---------------|-----------------------------|
| File open fail not caught | All import methods | Use `try-catch` for file open  |
| Sleep for company readiness | ensureCompanyExists | Poll/check with backoff instead of `sleep` |
| Gender field mapping | importEmployees | Robust switch/case or validation |
| Duplicate employee refs | importEmployees | Check with `isset` before insert |
| Salary component type | importSalaryStructures | Validate allowed types, skip invalid |
| Date parsing in salary data | importSalaryData | Check result of `createFromFormat` |
| Salary amount localization | parseSalaryAmount | Better cleaning and validation |
| Error/exception logging | All | Log exception object, use context |
| Fail counter for imports | All import methods | Keep `$failCount` and warn of errors |
| Strict types enforcement | Top of file | Add `declare(strict_types=1);` |

---

## **References / Pseudocode Corrections**

You can integrate all the above snippets directly into the relevant places in your code for improved robustness, maintainability, and clarity. If you need the actual code output for each method with corrections applied, please specify.