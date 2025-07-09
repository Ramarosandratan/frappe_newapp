# High-Level Documentation

This file is a Symfony service configuration file (typically named `services.yaml`) that defines how various services (i.e., PHP classes) in your application are managed and wired together by the Symfony Dependency Injection container.

## Key Features:

### 1. Parameters Block
- A placeholder for static configuration values (none are specified here, but the section header is present for future use).

### 2. Service Defaults
- **autowire**: Enabled, so that Symfony automatically injects dependencies into your services based on type hints.
- **autoconfigure**: Enabled, so services are automatically registered for special Symfony behaviors (e.g., commands, event listeners) if they implement relevant interfaces.

### 3. Automatic Service Registration
- All classes in the `src/` directory (`App\` namespace) are automatically registered as services. Each class gets a service ID identical to its fully-qualified class name.

### 4. Explicit Service Configuration
- **App\Service\ErpNextService** is explicitly configured to:
    - Receive four arguments:
        - `$apiBase`, `$apiKey`, and `$apiSecret`: Values from environment variables (`API_BASE`, `API_KEY`, `API_SECRET`).
        - `$logger`: A reference to the application's logger service.
    - This configuration will override (replace) any previous configuration for this class.

### 5. Extension Points
- The file encourages adding further explicit service configurations as needed and notes that later definitions override earlier ones.

---

**Summary:**  
This configuration file automates most service wiring for Symfony applications, while also allowing custom wiring of specific services, like `App\Service\ErpNextService`, using environment variables and service references for its dependencies.