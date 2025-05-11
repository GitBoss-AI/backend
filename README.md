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

`GET /api-dev/repo/stats?repo_url=github.com/vercel/next.js&time_window=7d`

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

### Contributor

`GET /api-dev/contributor/stats?github_username=octocat&repo_id=1&user_id=1&time_window=7d`

Returns contributor stats for a specific repo and user.

Query parameters:
- github_username: GitHub login of the contributor
- repo_id: ID of the repo the contributor is associated with
- user_id: ID of the user requesting (must own the repo)
- time_window: optional — `Nd`, `Nw`, or `Nm` for daily, weekly, or monthly stats

Example response:
```json
{
  "from": "2024-04-29",
  "to": "2024-05-06",
  "stats": {
    "commits": 12,
    "prs_opened": 3,
    "reviews": 4
  }
}
```

---

`GET /api-dev/contributor/topPerformers?repo_id=1&time_window=1w`

Returns top 10 contributors by commits, PRs, and reviews in a given repo.

Query parameters:
- repo_id: ID of the target repo
- time_window: optional — if not given, uses all-time data

Example response:
```json
{
  "top_committers": [
    { "github_username": "alice", "commits": 15, "prs_opened": 4, "reviews": 2 },
    ...
  ],
  "top_prs": [
    { "github_username": "bob", "commits": 5, "prs_opened": 7, "reviews": 3 },
    ...
  ],
  "top_reviewers": [
    { "github_username": "charlie", "commits": 4, "prs_opened": 2, "reviews": 10 },
    ...
  ]
}
```

---

`GET /api-dev/contributor/recent-activity?repo_id=1`

Returns the 10 most impactful contributor activity events (commits, PRs, reviews) for a given repo.

Query parameters:
- repo_id: ID of the target repo

Example response:
```json
{
  "recent_activity": [
    { "type": "commit", "username": "alice", "quantity": 5, "timestamp": "2024-05-10T01:00:00Z" },
    { "type": "review", "username": "bob", "quantity": 3, "timestamp": "2024-05-10T01:00:00Z" },
    ...
  ],
  "top_contributors": [
    "alice",
    "bob",
    "charlie"
  ]
}
```

---

### Team

`GET /api-dev/team/timeline?repo_id=1&group_by=week`

Returns a time series of commits, PRs, and reviews grouped by week, month, or quarter for the selected repo.

Query parameters:
- repo_id: ID of the target repo
- group_by: `week`, `month`, or `quarter`

Example response:
```json
[
  { "label": "2024-W17", "commits": 40, "prs": 10, "reviews": 6 },
  { "label": "2024-W18", "commits": 52, "prs": 13, "reviews": 9 },
  ...
]
```


