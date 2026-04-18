# LaraKube Architecture & Best Practices Guide

LaraKube is an opinionated CLI for Laravel developers to manage Kubernetes environments from development to deployment, following a **Container-First** philosophy.

## 🚀 Core Philosophy: Zero-Host Dependency
LaraKube assumes the host machine (Mac/Linux) is "clean."
- **No Local PHP/Node:** All project creation, dependency installation, and asset building happen inside isolated Docker containers.
- **UID/GID Mapping:** To prevent permission issues, the CLI maps the host's User ID and Group ID into the containers so generated files are owned by the developer.
- **Service Isolation:** PHP and Node.js tasks are strictly separated. The PHP container handles Laravel/Composer, while a dedicated Node container handles Vite/NPM.

## 🛠 Command Toolbelt

### `new {name}`
Creates a new Laravel project using the containerized installer. LaraKube orchestrates the entire setup, including .env configuration and feature installation.

### `init`
Gracefully prepares an existing Laravel application for its Kubernetes debut by generating all necessary infrastructure files.

### `up {environment}`
The primary deployment tool. Automatically builds local images, updates your hosts file, and deploys the cluster manifests.

### `down {environment}`
Tears down the environment and specifically cleans up cluster-scoped PersistentVolumes to ensure a clean state for the next session.

### `add {items}`
The "Evolution" tool. Effortlessly add new databases, features, object storage, or architectural blueprints to an existing project.

### `tunnel {service}`
Intelligent port-forwarding that automatically resolves local port conflicts and provides clear connection details for GUI clients.

### `art {commands}`
A high-speed shortcut for `exec php artisan ...`.

### `npm`/`pnpm`/`bun`/`yarn`
Direct shortcuts to run package manager commands inside the dedicated local Node pod.

### `php:ext {extension}`
Surgically injects new PHP extensions into your Dockerfile and configuration without manual editing.

## 🏗 Kubernetes Strategy

### Kustomize Architecture
We use Kustomize for a "Pure YAML" approach:
- **Base:** Standard resources (Deployments, Services, PVCs) shared across all environments.
- **Overlays:** Environment-specific overrides (e.g., `hostPath` for local, cloud disks for production).

### Networking Standards
- **Internal Port 8080:** All internal cluster traffic is standardized on Port 8080 (HTTP) to simplify health probes and SSL termination at the Ingress.
- **Project Isolation:** Each project is isolated in its own namespace: `{app-name}-{env}`.
- **Local Domains:** Automatic generation of local subdomains (`vite.`, `s3.`, `meilisearch.`) for specialized services.

## 🏛 Architectural Pillars

### Blueprints (The Foundation)
Choose your stack (Standard Laravel, Statamic, or FilamentPHP) and LaraKube automatically handles the specific PHP extensions and installation requirements for that ecosystem.

### Database Engines
Hardened, production-ready manifests for MySQL 8.4, MariaDB 11.8, and PostgreSQL 17.9. All local databases include specialized readiness probes to ensure the database is fully initialized before migrations run.

### Object Storage (S3-Compatible)
Integrated support for MinIO, SeaweedFS, and Garage. LaraKube automatically configures the Flysystem drivers and provides dedicated local dashboards.

## 🔐 Security
- **Zero-Secrets-in-Git:** Manifests never contain secrets. Configuration is dynamically injected from your local, git-ignored `.env` files into Kubernetes `Secret` resources.
- **Safe Persistence:** Local volumes use the `Retain` policy to ensure your data survives cluster restarts while still allowing the CLI to manage the Kubernetes objects.
