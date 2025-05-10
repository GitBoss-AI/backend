# GitBoss AI – Backend

This is the backend API codebase for the GitBoss AI project. It is built using PHP and PostgreSQL and deployed to a self-hosted server for both development and production environments.

## Development Workflow

### Environment Configuration

To set up your local development environment:

1. Clone the repository
2. Copy `.env.example` to `.env` and configure your database credentials
3. Run `composer install` to install dependencies

### PRs and Branching

- **Only open pull requests against the `dev` branch.**
- **Do not** open PRs against `main` — that branch is reserved for production.

### CI/CD Pipeline

#### PR Checks

When a PR is opened to `dev`, a GitHub Actions workflow will:

1. Run PHP linting to check for syntax errors
2. Validate composer.json
3. Only merge if all checks pass

#### Automatic Dev Deployment

- Once a PR is merged into `dev`, the dev server will automatically pull the latest code and install dependencies.
- The development version of the API is available at:

   **https://gitboss-ai.emirbosnak.com/api-dev**

## API Endpoints (Dev)

The backend API for the dev environment provides the following endpoints:

- `POST /api-dev/register` - Register a new user
- `POST /api-dev/login` - Authenticate a user
- `GET /api-dev/health` - Check API health status
