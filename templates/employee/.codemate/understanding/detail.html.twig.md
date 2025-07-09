# High-Level Documentation

## Overview
This is a Twig template for a web page that displays detailed information about a single employee as well as a list of their salary slips. The template extends a base layout and structures the information in a user-friendly format, using Bootstrap classes for styling.

## Features

1. **Employee Details Section**
   - Displays the employee's full name prominently.
   - Renders key employee attributes: 
     - Employee Number
     - Date of Joining (formatted as YYYY-MM-DD)
     - Company
     - Gender

2. **Salary Slips Table**
   - Presents a table listing the employee's salary slips.
   - For each slip, the table includes:
     - Month and Year (formatted as "Month YYYY")
     - Gross Pay (formatted as a number with two decimals and comma separators)
     - Net Pay (formatted similarly)
     - A button linking to the detailed payslip view.
   - Displays a message if there are no salary slips.

3. **Navigation**
   - Includes a button to return to the employee list page.

## Template Structure

- **Extends:** The template inherits from `base.html.twig`, ensuring consistent site structure and style.
- **Blocks:**
  - `title`: Sets the page title to "Employee Details".
  - `body`: Contains all the content described above.

## Dynamic Content

- Utilizes passed-in variables:
  - `employee`: The current employee object.
  - `salary_slips`: Collection of salary slip objects for the employee.

- Leverages Twig filters:
  - `date` for formatting dates.
  - `number_format` for currency amounts.

## Use Case

This template is intended for an employee management or HR system, for use on a "show details" or "employee profile" page, enabling users to view both personal details and payroll history at a glance.