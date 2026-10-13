CREATE TABLE IF NOT EXISTS Users (
    Id           INTEGER PRIMARY KEY AUTOINCREMENT,
    Username     TEXT UNIQUE NOT NULL,
    PasswordHash TEXT NOT NULL,
    Role         TEXT NOT NULL DEFAULT 'user',
    Department   TEXT NOT NULL DEFAULT 'General',
    Email        TEXT NOT NULL DEFAULT ''
);
