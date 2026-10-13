using Loam.Models;
using Microsoft.AspNetCore.Mvc;
using Newtonsoft.Json;

namespace Loam.Controllers;

public class AdminController : Controller
{
    private readonly LoamConfig _cfg;

    public AdminController(LoamConfig cfg) => _cfg = cfg;

    private bool Ia() =>
        HttpContext.Session.GetString("Role") == "admin";

    [HttpGet]
    public IActionResult Index()
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        if (!Ia())
            return Content("<h2>403 Forbidden — Admin only</h2>", "text/html");

        ViewBag.FlagLocal = _cfg.FlagLocal;
        return View();
    }

    [HttpGet]
    public IActionResult Config()
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        if (!Ia())
            return Content("<h2>403 Forbidden — Admin only</h2>", "text/html");
        return View("Import");
    }

    [HttpPost]
    public IActionResult Config(string payload)
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        if (!Ia())
            return Content("<h2>403 Forbidden — Admin only</h2>", "text/html");

        if (string.IsNullOrEmpty(payload))
        {
            ViewBag.Error = "Payload is required.";
            return View("Import");
        }

        try
        {
            var b = new JsonSerializerSettings { TypeNameHandling = TypeNameHandling.None };
            var r = JsonConvert.DeserializeObject<AppConfig>(payload, b);
            ViewBag.Success = $"Configuration imported: {r?.Name ?? "(unnamed)"}";
        }
        catch (Exception ex)
        {
            ViewBag.Error = $"Import failed: {ex.Message}";
        }

        return View("Import");
    }

    [HttpGet]
    public IActionResult Import()
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        if (!Ia())
            return Content("<h2>403 Forbidden — Admin only</h2>", "text/html");
        return View();
    }

    [HttpPost]
    public IActionResult Import(string payload)
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        if (!Ia())
            return Content("<h2>403 Forbidden — Admin only</h2>", "text/html");

        if (string.IsNullOrEmpty(payload))
        {
            ViewBag.Error = "Payload is required.";
            return View();
        }

        if (payload.Length > 10000)
        {
            ViewBag.Error = "Payload too large (max 10000 chars).";
            return View();
        }

        if (!payload.StartsWith("ew"))
        {
            ViewBag.Error = "Invalid payload format. Expected base64-encoded JSON configuration.";
            return View();
        }

        string b;
        try
        {
            b = System.Text.Encoding.UTF8.GetString(Convert.FromBase64String(payload));
        }
        catch
        {
            ViewBag.Error = "Invalid base64 encoding.";
            return View();
        }

        try
        {
            var r = JsonConvert.DeserializeObject<object>(b, new JsonSerializerSettings
            {
                TypeNameHandling = TypeNameHandling.All
            });

            ViewBag.Success = $"Configuration imported successfully. Loaded {r?.GetType().Name ?? "null"} object.";
        }
        catch (Exception ex)
        {
            ViewBag.Error = $"Import failed: {ex.Message}";
        }

        return View();
    }
}
