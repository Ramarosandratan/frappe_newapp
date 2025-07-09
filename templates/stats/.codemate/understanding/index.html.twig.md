# High-Level Documentation of the Code

**Purpose:**  
This Twig template renders a web page displaying salary statistics, including dynamic charts and a detailed monthly breakdown in a tabular format. It allows users to filter data by year.

---

## Key Features and Structure

### 1. **Page Inheritance and Title**
- The template extends a base layout (`base.html.twig`).
- Sets the page title as "Statistiques des Salaires".

### 2. **Main Content Block (`body`)**
- Displays a header: **"Statistiques des Salaires"**.

### 3. **Year Filter Form**
- Contains a dropdown list populated with available years (from `years` variable).
- Submits the form upon selection or via a filter button.
- The filtering action reloads the page with data for the selected year.

### 4. **Statistical Chart**
- If a `chart` variable is provided, renders an interactive chart displaying salary statistics for the selected year, using a predefined function `render_chart`.

### 5. **Monthly Salary Statistics Table**
- A responsive table presents months as rows and salary breakdown as columns:
    - **Mois (Month)**
    - **Salary Components:** Dynamically generated from `allComponents` (e.g., base salary, bonuses, etc.).
    - **Total Brut** (Total Gross)
    - **Total Déductions** (Total Deductions)
    - **Total Net** (Total Net Pay)
- Each cell is properly formatted for currency (French notation: comma as decimal separator, space as thousands separator).
- If no data exists for the selected year, it displays a corresponding message in the table.

---

## Variables and Data

- **`years`**: List of years with available data.
- **`selectedYear`**: The currently selected or filtered year.
- **`chart`**: Optional; if set, a chart visualization appears.
- **`allComponents`**: List of all salary components to display as columns.
- **`statsByMonth`**: Main data structure mapping each month to its salary data, including:
    - `components`: Values per salary component.
    - `gross_pay`: Gross salary total.
    - `total_deduction`: Total deductions.
    - `net_pay`: Net salary.

---

## Design and Usability

- Uses Bootstrap for styling, responsive grid, form, and table elements.
- Ensures data is clearly formatted and emphasizes key figures via font style and color.

---

## Summary

**This template provides an interactive and informative salary statistics dashboard, allowing users to filter data by year, visualize trends via charts, and explore detailed monthly breakdowns across all salary components.**