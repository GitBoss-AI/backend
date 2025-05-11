# GitBoss AI – Backend

This is the backend API codebase for the GitBoss AI project. It is built using PHP and PostgreSQL and deployed to a self-hosted server for both development and production environments.

## API Endpoints

The backend API for the dev environment provides the following endpoints:

### Health Check

`GET /api-dev/health`

Returns basic status to verify the backend is running.

Example response:
```json
{ "status": "ok" }
```

---

### Authentication

`POST /api-dev/login`

Authenticate a user. Returns a 

Request body:
```json
{
  "username": "emirbosnak",
  "password": "emir123"
}
```

Response:
```json
{ 
  "message": "Login successful",
  "user_id": db id of user
  "token": jwt token
  "expires": jwt expiration timestamp (1 hour)
}
```

---

`POST /api-dev/register`

Create a new user account and claim GitHub ownership(s).

Request body:
```json
{
  "username": "emirbosnak",
  "password": "emir123",
  "github_ownership": "owner1,owner2"
}
```

Note: `github_ownership` is a comma-separated list of GitHub usernames (repo owners).

---

### Repositories

`POST /api-dev/repo/add`

Add a new GitHub repository to be tracked.

Request body:
```json
{
  "user_id": 1,
  "repo_url": "github.com/vercel/next.js"
}
```
Note: add and retrieve without `https://` 

---

`GET /api-dev/repo/getAll?user_id=1`

List all repos tracked by a user.

Query parameters:
- user_id: the numeric ID of the system user

---

`GET /api-dev/repo/stats?repo_url=https://github.com/vercel/next.js&time_window=7d`

Get stats snapshot for a repository.

Query parameters:
- repo_url: full GitHub repo URL (must match what was added)
- time_window: optional — use formats like `7d`, `2w`, `1m`

Example response:
```json
{
  "from": "2024-04-29",
  "to": "2024-05-06",
  "stats": {
    "commits": 42,
    "open_prs": 5,
    "closed_prs": 12,
    "issues": 3,
    "reviews": 6
  }
}
```

