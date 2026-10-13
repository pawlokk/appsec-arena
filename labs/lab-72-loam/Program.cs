using Loam.BackgroundWorker;
using Microsoft.Data.Sqlite;

var builder = WebApplication.CreateBuilder(args);

string a = Environment.GetEnvironmentVariable("FLAG_LOCAL") ?? "ARENA{LOCAL_dev}";
string b = Environment.GetEnvironmentVariable("FLAG_PROOF") ?? "ARENA{PROOF_dev}";
string d = Environment.GetEnvironmentVariable("DB_PATH")    ?? "/app/loam.db";

builder.Services.AddSingleton(new LoamConfig
{
    FlagLocal = a,
    FlagProof = b,
    DbPath    = d
});

builder.Services.AddControllersWithViews();
builder.Services.AddHostedService<TaskProcessor>();
builder.Services.AddSession(o =>
{
    o.Cookie.HttpOnly  = true;
    o.Cookie.IsEssential = true;
    o.IdleTimeout      = TimeSpan.FromHours(2);
});
builder.Services.AddHttpClient();

var app = builder.Build();

using (var c = new SqliteConnection($"Data Source={d}"))
{
    c.Open();
    using var s = c.CreateCommand();
    s.CommandText = @"
        CREATE TABLE IF NOT EXISTS Users (
            Id           INTEGER PRIMARY KEY AUTOINCREMENT,
            Username     TEXT UNIQUE NOT NULL,
            PasswordHash TEXT NOT NULL,
            Role         TEXT NOT NULL DEFAULT 'user',
            Department   TEXT NOT NULL DEFAULT 'General',
            Email        TEXT NOT NULL DEFAULT ''
        );
        INSERT OR IGNORE INTO Users (Username, PasswordHash, Role, Department, Email)
            VALUES ('alice', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 'user', 'Engineering', 'alice@corp.internal');
        INSERT OR IGNORE INTO Users (Username, PasswordHash, Role, Department, Email)
            VALUES ('bob', '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 'user', 'HR', 'bob@corp.internal');
        INSERT OR IGNORE INTO Users (Username, PasswordHash, Role, Department, Email)
            VALUES ('admin', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a', 'admin', 'IT', 'admin@corp.internal');
        INSERT OR IGNORE INTO Users (Username, PasswordHash, Role, Department, Email)
            VALUES ('carol', '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92', 'user', 'Finance', 'carol@corp.internal');
        CREATE TABLE IF NOT EXISTS webhooks (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_id    INTEGER NOT NULL,
            tenant_id   TEXT NOT NULL DEFAULT 'default',
            url         TEXT NOT NULL,
            event_type  TEXT NOT NULL DEFAULT 'generic',
            payload_json TEXT NOT NULL DEFAULT '{}',
            active      INTEGER NOT NULL DEFAULT 1,
            created_at  TEXT DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS task_queue (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            webhook_id   INTEGER NOT NULL,
            payload_json TEXT NOT NULL,
            status       TEXT NOT NULL DEFAULT 'pending',
            result       TEXT,
            created_at   TEXT DEFAULT (datetime('now')),
            processed_at TEXT
        );
        INSERT OR IGNORE INTO webhooks (id, owner_id, tenant_id, url, event_type, payload_json)
            VALUES (1, 1, 'tenant-alpha', 'https://hooks.corp.internal/notify', 'user.created', '{}');
        INSERT OR IGNORE INTO webhooks (id, owner_id, tenant_id, url, event_type, payload_json)
            VALUES (2, 2, 'tenant-beta', 'https://hooks.corp.internal/hr', 'employee.updated', '{}');
        INSERT OR IGNORE INTO webhooks (id, owner_id, tenant_id, url, event_type, payload_json)
            VALUES (3, 3, 'tenant-gamma', 'https://hooks.corp.internal/admin', 'config.changed', '{}');
    ";
    s.ExecuteNonQuery();
}

app.UseStaticFiles();
app.UseRouting();
app.UseSession();
app.UseAuthorization();
app.MapControllerRoute("default", "{controller=Home}/{action=Index}/{id?}");

app.Run("http://0.0.0.0:5000");

public class LoamConfig
{
    public string FlagLocal { get; set; } = "";
    public string FlagProof { get; set; } = "";
    public string DbPath    { get; set; } = "";
}
