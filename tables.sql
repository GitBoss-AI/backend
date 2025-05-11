-- Drop tables if they already exist (in dependency order)
DROP TABLE IF EXISTS contributor_stats_snapshot CASCADE;
DROP TABLE IF EXISTS repo_stats_snapshot CASCADE;
DROP TABLE IF EXISTS repo_has_contributor CASCADE;
DROP TABLE IF EXISTS user_has_repo CASCADE;
DROP TABLE IF EXISTS contributors CASCADE;
DROP TABLE IF EXISTS repos CASCADE;
DROP TABLE IF EXISTS users CASCADE;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE github_ownerships (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    owner TEXT NOT NULL UNIQUE
);

CREATE TABLE repos (
    id SERIAL PRIMARY KEY,
    url TEXT NOT NULL UNIQUE,
    owner TEXT NOT NULL,
    name TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP,

    CONSTRAINT fk_repo_owner FOREIGN KEY (owner)
        REFERENCES github_ownerships(owner)
        ON DELETE CASCADE
);

CREATE TABLE contributors (
    id SERIAL PRIMARY KEY,
    github_username TEXT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE repo_has_contributor (
    id SERIAL PRIMARY KEY,
    repo_id INTEGER REFERENCES repos(id) ON DELETE CASCADE,
    contributor_id INTEGER REFERENCES contributors(id) ON DELETE CASCADE,
    first_seen DATE,
    last_seen DATE,
    UNIQUE (repo_id, contributor_id)
);

CREATE TABLE repo_stats (
    id SERIAL PRIMARY KEY,
    repo_id INTEGER REFERENCES repos(id) ON DELETE CASCADE,
    snapshot_date DATE NOT NULL,
    commits INTEGER DEFAULT 0,
    open_prs INTEGER DEFAULT 0,
    merged_prs INTEGER DEFAULT 0,
    open_issues INTEGER DEFAULT 0,
    reviews INTEGER DEFAULT 0,
    UNIQUE (repo_id, snapshot_date)
);

CREATE TABLE contributor_stats (
    id SERIAL PRIMARY KEY,
    contributor_id INTEGER REFERENCES contributors(id) ON DELETE CASCADE,
    repo_id INTEGER REFERENCES repos(id) ON DELETE CASCADE,
    snapshot_date DATE NOT NULL,
    commits INTEGER DEFAULT 0,
    prs_opened INTEGER DEFAULT 0,
    reviews INTEGER DEFAULT 0,
    UNIQUE (contributor_id, repo_id, snapshot_date)
);

CREATE INDEX idx_repo_snapshot_date ON repo_stats(repo_id, snapshot_date);
CREATE INDEX idx_contributor_snapshot_date ON contributor_stats_snapshot(repo_id, contributor_id, snapshot_date);
CREATE INDEX idx_repo_has_contributor ON repo_has_contributor(repo_id, contributor_id);
