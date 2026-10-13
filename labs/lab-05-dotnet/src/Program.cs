/**
 * AppSec Arena — lab-05-dotnet: Cipher
 * Stack: ASP.NET Core 8 · SQLite · Newtonsoft.Json
 *
 * Cipher — configuration and admin API
 */

using Microsoft.AspNetCore.Builder;
using Microsoft.AspNetCore.Http;
using Microsoft.AspNetCore.Mvc;
using System.Security.Cryptography;
using System.Text;
using Newtonsoft.Json;

var builder = WebApplication.CreateBuilder(args);
var app     = builder.Build();

// HMAC key for cookie signing
const string HMAC_KEY  = "cipher-secret-2024";
string FLAG_LOCAL = Environment.GetEnvironmentVariable("FLAG_LOCAL") ?? "ARENA{LOCAL_dev}";
string FLAG_PROOF = Environment.GetEnvironmentVariable("FLAG_PROOF") ?? "ARENA{PROOF_dev}";

// ── Helpers ───────────────────────────────────────────────────────────────────

string MakeCookie(string username, string role)
{
    var payload = Convert.ToBase64String(Encoding.UTF8.GetBytes($"{username}:{role}"));
    using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(HMAC_KEY));
    var sig = Convert.ToHexString(hmac.ComputeHash(Encoding.UTF8.GetBytes(payload))).ToLower();
    return $"{payload}.{sig}";
}

(string? username, string? role) ParseCookie(string? cookie)
{
    if (string.IsNullOrEmpty(cookie)) return (null, null);
    var parts = cookie.Split('.');
    if (parts.Length != 2) return (null, null);
    var (payload, sig) = (parts[0], parts[1]);
    using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(HMAC_KEY));
    var expected = Convert.ToHexString(hmac.ComputeHash(Encoding.UTF8.GetBytes(payload))).ToLower();
    if (!CryptographicOperations.FixedTimeEquals(
            Encoding.UTF8.GetBytes(expected), Encoding.UTF8.GetBytes(sig)))
        return (null, null);
    var decoded = Encoding.UTF8.GetString(Convert.FromBase64String(payload)).Split(':');
    return decoded.Length == 2 ? (decoded[0], decoded[1]) : (null, null);
}

// ── Static users ──────────────────────────────────────────────────────────────
var users = new Dictionary<string, (string password, string role)>
{
    ["alice"] = ("alicepass", "user"),
    ["bob"]   = ("bobpass",   "user"),
};

// ── Routes ────────────────────────────────────────────────────────────────────

app.MapGet("/", () => Results.Content("""
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Cipher</title>
<script>window.__ARENA_LAB__ = {
  id: 'lab-05-dotnet', name: 'Cipher', level: 1,
  tags: ['Cookie Tamper', 'Auth Bypass', 'Deserialization', 'RCE'],
  platform: 'http://localhost:8080'
};</script>
<style>
body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
h1{color:#5c8aff}pre{background:#1a1e2a;padding:1rem;border-radius:8px;overflow:auto}
a{color:#5c8aff}
</style></head>
<body>
<h1>Cipher</h1>
<p>Internal configuration portal. Cookie-authenticated.</p>
<pre>POST /api/login                  { "username": "...", "password": "..." }
GET  /api/profile                cookie: arena_session=...
GET  /api/admin/flag             admin role required
POST /api/admin/deserialize      admin — process configuration payload</pre>
<p>Users: alice / alicepass &nbsp; bob / bobpass</p>
</body></html>
""", "text/html"));

app.MapPost("/api/login", (HttpContext ctx, [FromBody] LoginRequest req) =>
{
    if (!users.TryGetValue(req.Username ?? "", out var entry) || entry.password != req.Password)
        return Results.Json(new { error = "Invalid credentials" }, statusCode: 401);
    var cookie = MakeCookie(req.Username!, entry.role);
    ctx.Response.Cookies.Append("arena_session", cookie, new CookieOptions { HttpOnly = true });
    return Results.Json(new { token = cookie, role = entry.role });
});

app.MapGet("/api/profile", (HttpContext ctx) =>
{
    var (username, role) = ParseCookie(ctx.Request.Cookies["arena_session"]);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);
    return Results.Json(new { username, role });
});

app.MapGet("/api/admin/flag", (HttpContext ctx) =>
{
    var (username, role) = ParseCookie(ctx.Request.Cookies["arena_session"]);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);
    if (role != "admin")  return Results.Json(new { error = "Admin only" },   statusCode: 403);
    return Results.Json(new { flag = FLAG_LOCAL });
});

// Deserialize configuration with full type support
// Process submitted configuration object
app.MapPost("/api/admin/deserialize", async (HttpContext ctx) =>
{
    var (username, role) = ParseCookie(ctx.Request.Cookies["arena_session"]);
    if (username == null) return Results.Json(new { error = "Unauthorised" }, statusCode: 401);
    if (role != "admin")  return Results.Json(new { error = "Admin only" },   statusCode: 403);

    using var reader = new StreamReader(ctx.Request.Body);
    var body = await reader.ReadToEndAsync();
    try
    {
        // Deserialize the submitted configuration
        var obj = JsonConvert.DeserializeObject(body, new JsonSerializerSettings
        {
            TypeNameHandling = TypeNameHandling.All,
        });
        return Results.Json(new { result = obj?.ToString() });
    }
    catch (Exception e)
    {
        return Results.Json(new { error = e.Message }, statusCode: 500);
    }
});

app.Run("http://0.0.0.0:5000");

record LoginRequest(string? Username, string? Password);
