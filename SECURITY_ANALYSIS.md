# Security Analysis Report - OverPHP Framework

## Executive Summary
OverPHP is a minimal API-first PHP framework. The overall security posture has been significantly improved through a comprehensive security audit and subsequent remediation. A dedicated `Security` module was introduced to centralize security functions, and various framework components were refactored to use it.

## Detailed Findings and Resolutions

### 1. Missing CSRF Protection
- **File/Line**: Framework-wide
- **Severity**: High
- **Status**: **Resolved**
- **Resolution**: Implemented CSRF protection in `src/Core/Security.php`. The `Router` now automatically validates CSRF tokens for all state-changing HTTP methods (POST, PUT, PATCH, DELETE). Tokens are expected in the `X-CSRF-Token` header or `_csrf_token` POST field.

### 2. Insecure Routing Prefix Replacement
- **File/Line**: `src/Core/Router.php` - `run()` method
- **Severity**: Low
- **Status**: **Resolved**
- **Resolution**: Refactored the router to only strip the prefix if it matches the beginning of the URI, using `str_starts_with` and `substr`. This prevents unintended path replacement.

### 3. Missing Security Headers
- **File/Line**: `index.php` and `src/Core/Security.php`
- **Severity**: Medium
- **Status**: **Resolved**
- **Resolution**: Added a `sendSecurityHeaders()` method to the `Security` module that sets essential headers: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, and a basic `Content-Security-Policy`. These are now automatically sent via `index.php`.

### 4. Potential Sensitive Information Leak in Database Errors
- **File/Line**: `src/Libs/Database.php`
- **Severity**: Low/Medium
- **Status**: **Resolved**
- **Resolution**: Updated the database layer to genericize exception messages stored in `lastError`. Detailed error messages are now logged to the system's error log using `error_log()`, preventing sensitive information from being accidentally exposed in API responses.

### 5. Insecure JSON Encoding Defaults
- **File/Line**: `src/Core/Router.php` and `src/Core/Security.php`
- **Severity**: Informational / Low
- **Status**: **Resolved**
- **Resolution**: Implemented a secure `jsonEncode()` method in the `Security` module that uses safe flags (`JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`). The `Router` now uses this method for all JSON responses.

### 6. Session Management Improvements
- **File/Line**: `src/Core/Security.php`
- **Severity**: Medium
- **Status**: **Resolved**
- **Resolution**: Added `startSecureSession()` and `regenerateSessionId()` methods to the `Security` module. These ensure sessions are started with secure cookie flags (`httponly`, `secure` where applicable, `samesite=Lax`) and provide a mechanism to prevent session fixation.

## New Security Module: `OverPHP\Core\Security`
A central security class was added to the framework core, providing:
- **CSRF Token Management**: `generateCsrfToken()`, `validateCsrfToken($token)`, `setCsrfEnabled($bool)`, `isCsrfEnabled()`
- **Secure Sessions**: `startSecureSession()`, `regenerateSessionId()`
- **Security Headers**: `sendSecurityHeaders()`
- **Output Sanitization**: `escape($data)` (for HTML), `jsonEncode($data)` (for JSON)

## Stateless (Token-based) vs Stateful (Cookie-based) Security

OverPHP now supports both security models for APIs:

### 1. Stateful (Session-based)
Ideal for web applications where the frontend and backend share the same domain or are closely coupled.
- **How to configure**: Set `'security' => ['csrf_enabled' => true]` in `config.php`.
- **How it works**: Uses PHP sessions and cookies. CSRF protection is **mandatory** and automatically enforced by the `Router` for POST, PUT, PATCH, and DELETE requests.
- **Developer action**: Must include the CSRF token in requests (e.g., via the `X-CSRF-Token` header).

### 2. Stateless (Token-based / JWT)
Ideal for pure REST or GraphQL APIs consumed by diverse clients (mobile apps, external web apps).
- **How to configure**: Set `'security' => ['csrf_enabled' => false]` in `config.php`.
- **How it works**: No sessions or cookies are used for authentication. Authentication is typically handled via an `Authorization: Bearer <token>` header.
- **CSRF**: Not required as browsers do not automatically send custom headers for cross-site requests.

## General Recommendations for Future Development
1. **Developer Education**: Ensure developers building on OverPHP are aware of the new security features and how to use them (e.g., using `Security::generateCsrfToken()` when rendering forms).
2. **Input Validation**: While output is now sanitized, developers should still be encouraged to use strict input validation for all user-provided data.
3. **Authentication**: When implementing authentication, always use modern, secure password hashing (e.g., `password_hash()` with Argon2 or bcrypt), which is natively supported in PHP 8.1+.
