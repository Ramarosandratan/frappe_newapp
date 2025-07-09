# Code Review Report

## Template: Twig (`statistiques.html.twig`)

---

### 1. **Industry Standards**

- **Semantic HTML:**  
  Use semantic `<section>`, `<header>`, etc., for enhanced accessibility.

- **Accessibility:**  
  - Form elements (e.g., `<select>`) should have `<label>`.
  - Table headers should use scope properly, and caption is recommended.

- **Form Usability:**  
  A submit button should be present even if `onchange` is used for accessibility and users without JavaScript.

#### **Suggested Changes:**

```pseudo
<!-- Add label for select input -->
<label for="yearSelect" class="sr-only">Année</label>
<select id="yearSelect" name="year" class="form-control" onchange="this.form.submit()">
```
---

### 2. **Unoptimized Implementations**

- The "Filtrer" button may be redundant as the filter submits on change, but should remain for accessibility.
- Use incremented template logic for repeated code, e.g., table columns.

#### **Suggested Changes:**

```pseudo
<!-- Add aria-label for table for accessibility -->
<table class="table ..." aria-label="Statistiques des Salaires">

<!-- Consider adding a <caption> -->
<caption>Statistiques mensuelles des salaires pour l'année sélectionnée</caption>
```
---

### 3. **Possible Errors**

- **Use of `number_format` filter**  
  - Check if the variables are always numbers before formatting, or provide default.

- **Column span calculation in empty result row**  
  - `allComponents|length + 4` may cause an error if `allComponents` is undefined or not an array.

#### **Suggested Changes:**

```pseudo
<!-- Add a default fallback for number_format -->
{{ data.gross_pay|default(0)|number_format(2, ',', ' ') }}
{{ data.total_deduction|default(0)|number_format(2, ',', ' ') }}
{{ data.net_pay|default(0)|number_format(2, ',', ' ') }}

<!-- Guard for colspan calculation -->
<td colspan="{{ (allComponents is defined ? allComponents|length : 0) + 4 }}" class="text-center">Aucune donnée à afficher.</td>
```

---

### 4. **General Twig Practice**

- **Use of `is defined`**  
  Use `is defined` before looping or accessing variables that might be undefined.

#### **Suggested Changes:**

```pseudo
{% if statsByMonth is defined %}
    {% for month, data in statsByMonth %}
        ...
    {% else %}
        ...
    {% endfor %}
{% endif %}
```
---

## **Summary Table of Corrections**

| Issue                 | Code Snippet (Pseudo)                                              | Remark                           |
|-----------------------|--------------------------------------------------------------------|----------------------------------|
| No label for select   | `<label for="yearSelect">Année</label>` ... `<select id="yearSelect" ...>` | For accessibility               |
| Table accessibility   | `<table ... aria-label="Statistiques des Salaires">`               | Improves screen reader navigation |
| Add caption           | `<caption>Statistiques mensuelles ...</caption>`                   | Recommended for tables           |
| Format with fallback  | `{{ var|default(0)|number_format(2, ',', ' ') }}`                  | Prevents errors                  |
| Colspan guard         | `<td colspan="{{ (allComponents is defined ? allComponents|length : 0) + 4 }}">` | Prevents errors on undefined     |

---

> **Note:**  
> Security, business logic, and controller-side (PHP) issues are not covered in this template-only review.

---

**End of report.**