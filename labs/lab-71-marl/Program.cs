using System;
using System.IO;
using System.Text;
using System.Xml;
using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.Data.Sqlite;
using Marl;
using Marl.Models;

var builder = WebApplication.CreateBuilder(args);

builder.Services.AddCors(o => {
    o.AddPolicy("Public", p => p
        .SetIsOriginAllowed(origin => origin != null && origin.EndsWith(".marl.internal"))
        .AllowCredentials()
        .AllowAnyMethod()
        .AllowAnyHeader());
    o.AddDefaultPolicy(p => p
        .SetIsOriginAllowed(origin => origin != null && origin.EndsWith(".marl.internal"))
        .AllowCredentials()
        .AllowAnyMethod()
        .AllowAnyHeader());
});

var app = builder.Build();

app.UseCors();

string FLAG_LOCAL = Environment.GetEnvironmentVariable("FLAG_LOCAL") ?? "ARENA{LOCAL_dev}";
string FLAG_PROOF = Environment.GetEnvironmentVariable("FLAG_PROOF") ?? "ARENA{PROOF_dev}";
string DB_PATH    = Environment.GetEnvironmentVariable("DB_PATH") ?? "/app/marl.db";
string SESSION_SECRET = Environment.GetEnvironmentVariable("SESSION_SECRET") ?? "marl-session-key-default";

void Idb()
{
    using var c = new SqliteConnection($"Data Source={DB_PATH}");
    c.Open();
    using var s = c.CreateCommand();
    s.CommandText = @"
        CREATE TABLE IF NOT EXISTS users (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            username            TEXT UNIQUE NOT NULL,
            password_hash       TEXT NOT NULL,
            role                TEXT NOT NULL DEFAULT 'user',
            workflowProfile     TEXT NOT NULL DEFAULT 'standard',
            integration_options TEXT NOT NULL DEFAULT '{}'
        );
        CREATE TABLE IF NOT EXISTS import_jobs (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            owner         TEXT NOT NULL,
            status        TEXT NOT NULL DEFAULT 'pending',
            status_detail TEXT,
            result        TEXT,
            created_at    TEXT DEFAULT (datetime('now'))
        );
        INSERT OR IGNORE INTO users (username, password_hash, role)
            VALUES ('alice', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 'user');
        INSERT OR IGNORE INTO users (username, password_hash, role)
            VALUES ('bob',   '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8', 'user');
        INSERT OR IGNORE INTO users (username, password_hash, role)
            VALUES ('admin', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'admin');
    ";
    s.ExecuteNonQuery();
}

Idb();

string H256(string a)
{
    using var x = System.Security.Cryptography.SHA256.Create();
    return Convert.ToHexString(x.ComputeHash(Encoding.UTF8.GetBytes(a))).ToLower();
}

string Msc(UserSession a)
{
    var b = $"{a.Username}|{a.Role}|{a.IssuedAt:O}";
    var c = Encoding.UTF8.GetBytes(b);
    using var d = new System.Security.Cryptography.HMACSHA256(Encoding.UTF8.GetBytes(SESSION_SECRET));
    var e = Convert.ToBase64String(d.ComputeHash(c));
    return Convert.ToBase64String(Encoding.UTF8.GetBytes($"{b}:{e}"));
}

UserSession? Psc(string? a)
{
    if (string.IsNullOrEmpty(a)) return null;
    try
    {
        var b = Encoding.UTF8.GetString(Convert.FromBase64String(a));
        var c = b.LastIndexOf(':');
        if (c < 0) return null;
        var d = b[..c];
        var e = b[(c + 1)..];
        using var f = new System.Security.Cryptography.HMACSHA256(Encoding.UTF8.GetBytes(SESSION_SECRET));
        var g = Convert.ToBase64String(f.ComputeHash(Encoding.UTF8.GetBytes(d)));
        if (e != g) return null;
        var h = d.Split('|');
        if (h.Length < 3) return null;
        return new UserSession { Username = h[0], Role = h[1], IssuedAt = DateTime.Parse(h[2]) };
    }
    catch { return null; }
}

void Lp(HttpContext a)
{
    var b = a.Request.Cookies["marl_pref"];
    if (string.IsNullOrEmpty(b)) return;
    try
    {
        var c = new System.Xml.XmlDocument();
        c.LoadXml(b);
        var d = c.SelectNodes("/pref/item");
        if (d == null) return;
        foreach (System.Xml.XmlNode e in d)
        {
            var f = e.Attributes?["type"]?.Value;
            if (string.IsNullOrEmpty(f)) continue;
            var g = Type.GetType(f);
            if (g == null) continue;
            var h = g.IsGenericType ? g.GetGenericArguments() : Array.Empty<Type>();
            Console.Error.WriteLine("[LP] h=" + string.Join(", ", System.Array.ConvertAll(h, t => t.FullName)));
            var i = new System.Xml.Serialization.XmlSerializer(g, h);
            using var j = new System.IO.StringReader(e.InnerXml);
            i.Deserialize(j);
        }
    }
    catch (Exception ex) { Console.Error.WriteLine("[LP] " + ex); }
}

app.Use(async (a, b) => { Lp(a); await b(a); });

(string? username, string? role) Gs(HttpContext a)
{
    var b = a.Request.Cookies["marl_session"]
          ?? a.Request.Headers["X-Session-Token"].ToString();
    if (string.IsNullOrEmpty(b)) return (null, null);
    var c = Psc(b);
    if (c == null) return (null, null);
    return (c.Username, c.Role);
}

app.MapGet("/", () => Results.Content("""
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marl — Enterprise API Gateway</title>
<script>window.__ARENA_LAB__ = {
  id: 'lab-71-marl', name: 'Marl', level: 5,
  tags: ['enterprise', 'api-gateway', 'multi-chain'],
  platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}h2{color:#8fb3ff}pre{background:#1a1e2a;padding:1rem;border-radius:8px;overflow:auto}
a{color:#5c8aff}.badge{background:#1a1e2a;padding:2px 8px;border-radius:4px;font-size:.8em}
</style>
</head>
<body>
<h1>Marl</h1>
<p>Enterprise API Gateway &amp; Document Management System</p>
<h2>Authentication</h2>
<pre>POST /api/login                      { "username": "...", "password": "..." }
     → sets marl_session cookie (or use X-Session-Token header)</pre>
<h2>Documents</h2>
<pre>POST /api/documents/validate         Content-Type: application/xml  — validate XML document
POST /api/documents/import           Content-Type: application/xml  — import XML document
GET  /api/documents                  list your documents</pre>
<h2>System</h2>
<pre>GET  /api/health                     service health check</pre>
<p>Test accounts: <span class="badge">alice / password</span> <span class="badge">bob / password123</span></p>
</body>
</html>
""", "text/html"));

app.MapPost("/api/login", async (HttpContext a) =>
{
    using var b = new StreamReader(a.Request.Body);
    var c = await b.ReadToEndAsync();
    LoginRequest? d = null;
    try { d = System.Text.Json.JsonSerializer.Deserialize<LoginRequest>(c); }
    catch { return Results.Json(new { error = "Invalid JSON" }, statusCode: 400); }

    if (d?.Username == null || d.Password == null)
        return Results.Json(new { error = "Missing credentials" }, statusCode: 400);

    using var e = new SqliteConnection($"Data Source={DB_PATH}");
    e.Open();
    using var f = e.CreateCommand();
    f.CommandText = "SELECT role FROM users WHERE username=@u AND password_hash=@p";
    f.Parameters.AddWithValue("@u", d.Username);
    f.Parameters.AddWithValue("@p", H256(d.Password));
    var g = f.ExecuteScalar()?.ToString();

    if (g == null)
        return Results.Json(new { error = "Invalid credentials" }, statusCode: 401);

    var h = new UserSession { Username = d.Username, Role = g };
    var t = Msc(h);
    a.Response.Cookies.Append("marl_session", t, new CookieOptions { HttpOnly = true });
    return Results.Json(new { token = t, role = g });
});

app.MapGet("/api/profile", (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);
    return Results.Json(new { username, role });
});

app.MapPost("/api/documents/validate", async (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    var b = a.Request.ContentType ?? "";
    if (!b.Contains("application/xml") && !b.Contains("text/xml"))
        return Results.Json(new { error = "Content-Type must be application/xml" }, statusCode: 400);

    using var c = new StreamReader(a.Request.Body);
    var d = await c.ReadToEndAsync();

    try
    {
        var e = new XmlReaderSettings
        {
            DtdProcessing = DtdProcessing.Prohibit,
            XmlResolver   = null
        };
        using var f = XmlReader.Create(new StringReader(d), e);
        while (f.Read()) {}
    }
    catch (XmlException g)
    {
        return Results.Json(new { error = $"Invalid XML: {g.Message}" }, statusCode: 400);
    }

    return Results.Ok(new { valid = true });
});

app.MapPost("/api/config/import", async (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    using var b = new StreamReader(a.Request.Body);
    var c = await b.ReadToEndAsync();

    ConfigImportDto? d;
    try
    {
        d = System.Text.Json.JsonSerializer.Deserialize<ConfigImportDto>(c);
    }
    catch (Exception e)
    {
        return Results.Json(new { error = $"Invalid JSON: {e.Message}" }, statusCode: 400);
    }

    return Results.Json(new { status = "imported", name = d?.Name ?? "(unnamed)", version = d?.Version ?? "0" });
});

app.MapGet("/public/{**path}", (HttpContext a, string path) =>
    Results.Json(new { path, status = "ok", note = "public endpoint" })
).RequireCors("Public");

app.MapPost("/api/documents/import", async (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    var b = a.Request.ContentType ?? "";
    if (!b.Contains("application/xml") && !b.Contains("text/xml"))
        return Results.Json(new { error = "Content-Type must be application/xml" }, statusCode: 400);

    using var c = new StreamReader(a.Request.Body);
    var d = await c.ReadToEndAsync();

    string? e = null;
    string? f = null;

    try
    {
        var g = new XmlDocument();
        g.XmlResolver = new XmlUrlResolver();
        g.LoadXml(d);

        e = g.SelectSingleNode("/document/title")?.InnerText;
        f = g.SelectSingleNode("/document/content")?.InnerText;
    }
    catch (XmlException h)
    {
        return Results.Json(new { error = $"XML parse error: {h.Message}" }, statusCode: 400);
    }

    return Results.Json(new
    {
        status  = "imported",
        title   = e ?? "(no title)",
        content = f ?? "(no content)",
        owner   = username,
    });
});

app.MapGet("/api/documents", (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);
    return Results.Json(new { documents = Array.Empty<object>() });
});

app.MapGet("/admin", (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null)
        return Results.Content("<h1>401 Unauthorised</h1><p>Set marl_session cookie.</p>", "text/html", statusCode: 401);
    if (role != "admin")
        return Results.Content("<h1>403 Forbidden</h1><p>Admin only.</p>", "text/html", statusCode: 403);

    return Results.Content($$"""
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Admin Dashboard</title>
<style>body{font-family:monospace;background:#0d0f14;color:#d4d8e8;padding:2rem}
h1{color:#5c8aff}.flag{background:#1a1e2a;padding:1rem;border-radius:8px;word-break:break-all}</style>
</head><body>
<h1>Admin Dashboard</h1>
<p>Welcome, {{System.Net.WebUtility.HtmlEncode(username)}}.</p>
<div class="flag"><strong>FLAG_LOCAL:</strong> {{System.Net.WebUtility.HtmlEncode(FLAG_LOCAL)}}</div>
</body></html>
""", "text/html");
});

app.MapGet("/api/admin/users", (HttpContext a) =>
{
    var (username, role) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);
    if (role != "admin")  return Results.Json(new { error = "Admin only" },   statusCode: 403);

    var b = new System.Collections.Generic.List<object>();
    using var c = new SqliteConnection($"Data Source={DB_PATH}");
    c.Open();
    using var d = c.CreateCommand();
    d.CommandText = "SELECT id, username, password_hash, role FROM users";
    using var e = d.ExecuteReader();
    while (e.Read())
        b.Add(new { id = e.GetInt32(0), username = e.GetString(1), passwordHash = e.GetString(2), role = e.GetString(3) });
    return Results.Json(b);
});

app.MapGet("/api/health", () =>
    Results.Json(new { status = "ok", service = "marl-api-gateway", version = "2.4.1" }));

app.MapGet("/api/integrations/bootstrap", (HttpContext a) =>
{
    var b = new UserSession { Username = "admin", Role = "admin" };
    var t = Msc(b);
    return Results.Json(new { bootstrapValue = t });
});

app.MapPost("/api/profile/update", async (HttpContext a) =>
{
    var (username, _) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    using var b = new StreamReader(a.Request.Body);
    var c = await b.ReadToEndAsync();
    System.Text.Json.JsonDocument? d = null;
    try { d = System.Text.Json.JsonDocument.Parse(c); }
    catch { return Results.Json(new { error = "Invalid JSON" }, statusCode: 400); }

    var e = d.RootElement;
    string? f = e.TryGetProperty("displayName",     out var dn) ? dn.GetString() : null;
    string? g = e.TryGetProperty("workflowProfile", out var dm) ? dm.GetString() : null;

    using var h = new SqliteConnection($"Data Source={DB_PATH}");
    h.Open();
    using var i = h.CreateCommand();
    if (g != null)
    {
        i.CommandText = "UPDATE users SET workflowProfile = $mode WHERE username = $username";
        i.Parameters.AddWithValue("$mode", g);
        i.Parameters.AddWithValue("$username", username);
        i.ExecuteNonQuery();
    }
    return Results.Json(new { success = true, updated = new { displayName = f, workflowProfile = g } });
});

app.MapPost("/api/documents/import/v2", async (HttpContext a) =>
{
    var (username, _) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    var b = a.Request.ContentType ?? "";
    if (!b.Contains("application/xml") && !b.Contains("text/xml"))
        return Results.Json(new { error = "Content-Type must be application/xml" }, statusCode: 400);

    using var c = new StreamReader(a.Request.Body);
    var d = await c.ReadToEndAsync();

    long ji;
    using (var e = new SqliteConnection($"Data Source={DB_PATH}"))
    {
        e.Open();
        using var f = e.CreateCommand();
        f.CommandText = "INSERT INTO import_jobs (owner, status) VALUES ($owner, 'processing'); SELECT last_insert_rowid();";
        f.Parameters.AddWithValue("$owner", username);
        ji = (long)(f.ExecuteScalar() ?? 0L);
    }

    string? g = null; string? h = null; string? sd = null;
    try
    {
        var i = new XmlDocument();
        i.XmlResolver = new XmlUrlResolver();
        i.LoadXml(d);
        g = i.SelectSingleNode("/document/title")?.InnerText;
        h = i.SelectSingleNode("/document/content")?.InnerText;
    }
    catch (Exception j)
    {
        sd = j.Message;
    }

    using (var e = new SqliteConnection($"Data Source={DB_PATH}"))
    {
        e.Open();
        using var k = e.CreateCommand();
        k.CommandText = "UPDATE import_jobs SET status = $status, status_detail = $detail, result = $res WHERE id = $id";
        k.Parameters.AddWithValue("$status", sd != null ? "failed" : "completed");
        k.Parameters.AddWithValue("$detail", sd ?? (object)DBNull.Value);
        k.Parameters.AddWithValue("$res", g ?? "(no title)");
        k.Parameters.AddWithValue("$id", ji);
        k.ExecuteNonQuery();
    }

    return Results.Json(new { job_id = ji, status = sd != null ? "failed" : "completed" });
});

app.MapGet("/api/import/status/{id}", (HttpContext a, long id) =>
{
    var (username, _) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    using var b = new SqliteConnection($"Data Source={DB_PATH}");
    b.Open();
    using var c = b.CreateCommand();
    c.CommandText = "SELECT id, owner, status, status_detail, result, created_at FROM import_jobs WHERE id = $id";
    c.Parameters.AddWithValue("$id", id);
    using var d = c.ExecuteReader();
    if (!d.Read()) return Results.Json(new { error = "not found" }, statusCode: 404);
    var e = d.IsDBNull(3) ? null : d.GetString(3);
    return Results.Json(new {
        id            = d.GetInt64(0),
        owner         = d.GetString(1),
        status        = d.GetString(2),
        status_detail = e != null && e.Length > 512 ? e[..512] + "..." : e,
        result        = d.IsDBNull(4) ? null : d.GetString(4),
        created_at    = d.GetString(5),
    });
});

app.MapPost("/api/profile/integrations", async (HttpContext a) =>
{
    var (username, _) = Gs(a);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);

    IntegrationOptions b;
    try { b = await a.Request.ReadFromJsonAsync<IntegrationOptions>() ?? new IntegrationOptions(); }
    catch { return Results.Json(new { error = "Invalid JSON" }, statusCode: 400); }

    var c = System.Text.Json.JsonSerializer.Serialize(b);
    using var d = new SqliteConnection($"Data Source={DB_PATH}");
    d.Open();
    using var e = d.CreateCommand();
    e.CommandText = "UPDATE users SET integration_options = $opts WHERE username = $username";
    e.Parameters.AddWithValue("$opts", c);
    e.Parameters.AddWithValue("$username", username);
    e.ExecuteNonQuery();
    return Results.Json(new { success = true, options = b });
});

app.Run("http://0.0.0.0:5000");

record LoginRequest(string? Username, string? Password);
