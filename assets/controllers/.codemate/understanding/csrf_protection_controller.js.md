---
# High-Level Documentation

## Purpose
This code provides client-side CSRF (Cross-Site Request Forgery) protection mechanisms for form submissions in a web application, with integration for Symfony’s SameOriginCsrfTokenManager and compatibility with Hotwire Turbo forms.

---

## Main Features

1. **Form Field and Cookie CSRF Double-Submit**
   - On every form submission, a CSRF token is generated and **placed both in a form field and as a cookie** (double submit technique).
   - Ensures that the CSRF token is available both in form data and as a cookie, as required for SameOrigin CSRF protection.

2. **Compatibility with Hotwire Turbo**
   - When forms are submitted using Hotwire Turbo, the CSRF token is:
     - Added to an HTTP header (if the appropriate Symfony config is enabled).
     - Removed from the cookie after Turbo form submission for cleanup.

3. **Token Generation and Management**
   - If a CSRF token field is detected in a form, it:
     - Ensures the token meets specific validation criteria (alphanumeric, length).
     - Creates a cryptographically-strong random token if needed.
     - Sets and synchronizes the token value between the form field and cookie.

4. **Security Considerations**
   - Properly scopes the CSRF cookie using `Path=/` and `SameSite=Strict`, and, on HTTPS, uses the `__Host-` prefix and `Secure` attribute.

---

## API Overview

The following core functions are exposed:

### `generateCsrfToken(formElement)`
- Finds the CSRF token field in the given form.
- If a valid cookie/csrf token does not exist, generates a new secure random token.
- Stores the token in the form and as a secure cookie.

### `generateCsrfHeaders(formElement)`
- For use with JavaScript-based form submissions (like Hotwire Turbo).
- Returns an object with the CSRF token as an HTTP header (used if server-side configuration allows CSRF tokens in headers).

### `removeCsrfToken(formElement)`
- Clears the CSRF cookie associated with the form's token (typically after Turbo form submissions).

---

## Integration Points

- **Event Listeners:**  
  - Plain form submissions: Sets the CSRF tokens.
  - Turbo submissions: Adds the token to headers and removes the cookie post-submit.

- **Configuration Dependency:**  
  - The CSRF token header is only checked server-side if Symfony’s `framework.csrf_protection.check_header` is enabled.

---

## Security Practices

- **Strict Cookie Settings:**  
  - Uses `SameSite=Strict`.
  - Uses `Secure` and `__Host-` with HTTPS.
- **Token Validation:**  
  - Names and token values are checked with constrained regular expressions for format/length.

---

## Usage

- **Automatic:**  
  - Upon form submission, the protection and cleanup routines are triggered for any form containing an appropriate CSRF token field.

- **With Turbo:**  
  - Additional handling ensures tokens are included as headers and cookies are removed as needed.

---

## Default Export

- The module exports a string identifier `'csrf-protection-controller'` for use with Stimulus.js or similar frameworks.

---

**In summary:**  
This module robustly implements a CSRF protection workflow for modern web apps, supporting both standard and Turbo-based form submissions, setting tokens securely in cookies and headers, and allowing seamless integration with Symfony’s CSRF mechanisms.