using Loam.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Data.Sqlite;

namespace Loam.Controllers;

public class WebhookController : Controller
{
    private readonly LoamConfig _cfg;

    public WebhookController(LoamConfig cfg) => _cfg = cfg;

    private int? Gui()
    {
        var a = HttpContext.Session.GetString("UserId");
        return int.TryParse(a, out var b) ? b : null;
    }

    private string? Gti() => HttpContext.Session.GetString("TenantId");

    [HttpGet]
    public IActionResult Index()
    {
        if (Gui() == null) return RedirectToAction("Login", "Account");
        return View(Lw(Gui()!.Value));
    }

    [HttpPost]
    public IActionResult Create(string url, string eventType)
    {
        if (Gui() == null) return RedirectToAction("Login", "Account");
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        var s = c.CreateCommand();
        s.CommandText = @"
            INSERT INTO webhooks (owner_id, tenant_id, url, event_type, payload_json, active)
            VALUES ($oid, $tid, $url, $et, '{}', 1);
            SELECT last_insert_rowid();";
        s.Parameters.AddWithValue("$oid", Gui()!.Value);
        s.Parameters.AddWithValue("$tid", Gti() ?? "default");
        s.Parameters.AddWithValue("$url", url ?? "");
        s.Parameters.AddWithValue("$et", eventType ?? "generic");
        var i = (long)(s.ExecuteScalar() ?? 0L);

        Et(i, "{}");
        return RedirectToAction("Index");
    }

    [HttpGet]
    public IActionResult Edit(int id)
    {
        if (Gui() == null) return RedirectToAction("Login", "Account");
        var w = Gw(id);
        if (w == null) return NotFound();
        return View(w);
    }

    [HttpPost]
    public IActionResult Update(int id, string url, string payloadJson)
    {
        if (Gui() == null) return RedirectToAction("Login", "Account");

        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        var s = c.CreateCommand();
        s.CommandText = "UPDATE webhooks SET url = $url, payload_json = $pj WHERE id = $id";
        s.Parameters.AddWithValue("$url", url ?? "");
        s.Parameters.AddWithValue("$pj", payloadJson ?? "{}");
        s.Parameters.AddWithValue("$id", id);
        s.ExecuteNonQuery();

        Et(id, payloadJson ?? "{}");
        return RedirectToAction("Index");
    }

    private void Et(long a, string b)
    {
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        var s = c.CreateCommand();
        s.CommandText = "INSERT INTO task_queue (webhook_id, payload_json, status) VALUES ($wid, $pj, 'pending')";
        s.Parameters.AddWithValue("$wid", a);
        s.Parameters.AddWithValue("$pj", b);
        s.ExecuteNonQuery();
    }

    [HttpGet]
    public IActionResult TaskStatus()
    {
        if (Gui() == null) return Unauthorized();
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        var s = c.CreateCommand();
        s.CommandText = "SELECT id, webhook_id, status, result, created_at, processed_at FROM task_queue ORDER BY created_at DESC LIMIT 20";
        var r = new System.Collections.Generic.List<object>();
        using var x = s.ExecuteReader();
        while (x.Read())
            r.Add(new {
                id = x.GetInt64(0),
                webhook_id = x.GetInt64(1),
                status = x.GetString(2),
                result = x.IsDBNull(3) ? null : x.GetString(3),
                created_at = x.IsDBNull(4) ? null : x.GetString(4),
                processed_at = x.IsDBNull(5) ? null : x.GetString(5),
            });
        return Json(r);
    }

    [HttpPost]
    public IActionResult Verify(int id)
    {
        var a = Gui();
        if (a == null) return Unauthorized();

        var w = Gw(id);
        if (w == null) return NotFound();
        if (w.OwnerId != a.Value && w.TenantId != Gti())
            return StatusCode(403, "Forbidden");

        return Json(new { id = w.Id, url = w.Url, active = w.Active, status = "verified" });
    }

    private WebhookModel? Gw(int a)
    {
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        var s = c.CreateCommand();
        s.CommandText = "SELECT id, owner_id, tenant_id, url, event_type, payload_json, active FROM webhooks WHERE id = $id";
        s.Parameters.AddWithValue("$id", a);
        using var x = s.ExecuteReader();
        if (!x.Read()) return null;
        return new WebhookModel {
            Id = (int)x.GetInt64(0),
            OwnerId = (int)x.GetInt64(1),
            TenantId = x.GetString(2),
            Url = x.GetString(3),
            EventType = x.GetString(4),
            PayloadJson = x.GetString(5),
            Active = x.GetInt32(6) == 1,
        };
    }

    private System.Collections.Generic.List<WebhookModel> Lw(int a)
    {
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        var s = c.CreateCommand();
        s.CommandText = "SELECT id, owner_id, tenant_id, url, event_type, payload_json, active FROM webhooks WHERE owner_id = $oid";
        s.Parameters.AddWithValue("$oid", a);
        var r = new System.Collections.Generic.List<WebhookModel>();
        using var x = s.ExecuteReader();
        while (x.Read())
            r.Add(new WebhookModel {
                Id = (int)x.GetInt64(0),
                OwnerId = (int)x.GetInt64(1),
                TenantId = x.GetString(2),
                Url = x.GetString(3),
                EventType = x.GetString(4),
                PayloadJson = x.GetString(5),
                Active = x.GetInt32(6) == 1,
            });
        return r;
    }
}
