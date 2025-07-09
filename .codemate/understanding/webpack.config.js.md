# High-Level Documentation

This code configures the Webpack asset bundler for a Symfony project using the Symfony Webpack Encore library. It defines how JavaScript and related assets should be compiled and output for use in a web application. The main responsibilities and features are:

---

## Purpose

- **Manages Frontend Assets**: Bundles JavaScript (and possibly other assets) for efficient delivery in a Symfony application.
- **Environment-Aware**: Adapts build behavior based on the current environment (development or production).
- **Simplifies Webpack Configuration**: Uses Webpack Encore to provide a more readable and maintainable configuration syntax.

---

## Key Features

- **Output Settings**: 
  - Compiled files are placed in `public/build/`.
  - Served under the `/build` public path.

- **Entry Points**: 
  - Main JavaScript entry is `./assets/app.js` labeled as `app`.

- **Optimization**: 
  - Splits entry chunks for better caching and efficiency.
  - Enables a single shared runtime chunk.

- **Build Maintenance**:
  - Cleans the output directory before building.
  - Displays system notifications on build events.

- **Debugging & Production**:
  - Generates source maps in non-production environments for easier debugging.
  - Adds hashes to filenames ("versioning") in production for cache busting.

- **JavaScript Features**:
  - Configures Babel to intelligently use polyfills (`core-js 3.23`) only as needed for target browser support.

- **Symfony Integration**:
  - Enables Stimulus controllers integration via a `controllers.json` manifest for Symfony UX support.

---

## Output

- **Exports Final Webpack Configuration**: The configuration is exported for use by Webpack when building assets.

---

In summary, this code sets up a modern, optimized asset build pipeline tailored for a Symfony project, focusing on best practices for performance, maintainability, and developer experience.