# Contributing to LaraKube CLI

Thank you for helping us craft the future of Laravel on Kubernetes! To ensure the project remains robust and maintainable, please follow these guidelines.

## 🎨 UI Consistency

LaraKube uses a custom output system built with **Termwind**. Every command should provide clear, branded feedback so the Artisan knows which step is currently running.

### Using `LaraKubeOutput`
All command classes must use the `App\Traits\LaraKubeOutput` trait.

- **Status Updates:** Use `$this->laraKubeInfo("Message")` for standard steps.
- **Failures:** Use `$this->laraKubeError("Message")` for errors.
- **Header:** Always call `$this->renderHeader()` at the start of the `handle()` method.

## 🏗 Modular Architecture (The Lego System)

LaraKube is designed to be modular. Features and Database engines are built as independent **Actions**.

- **Adding a Feature:** Create a new class in `app/Actions` that implements the `FeatureAction` interface.
- **Enums:** Map your new action in the `LaravelFeature` or `DatabaseEngine` enums.
- **Zero-Host Rule:** Ensure your actions run inside Docker containers using the `InteractsWithDocker` trait.

## 🛠 Local Development

1. **Active Hooks:** LaraKube uses Git hooks to keep the code clean. Activate them once:
   ```bash
   git config core.hooksPath .githooks
   ```
2. **Linting:** We use **Laravel Pint**. Your code will be automatically checked on commit, but you can run it manually:
   ```bash
   ./vendor/bin/pint
   ```

## 🧪 Deployment Testing
When adding a feature, please test it in a real cluster or using **OrbStack / Docker Desktop** to ensure the Kubernetes manifests are valid.
