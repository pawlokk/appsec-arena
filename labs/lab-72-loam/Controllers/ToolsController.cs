using Microsoft.AspNetCore.Mvc;

namespace Loam.Controllers;

public class ToolsController : Controller
{
    private readonly IHttpClientFactory _httpFactory;

    public ToolsController(IHttpClientFactory httpFactory) => _httpFactory = httpFactory;

    private static readonly HashSet<string> AllowedDomains = new() { "example.com", "test.org", "docs.io" };

    [HttpPost]
    public async Task<IActionResult> CheckUrl(string url)
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");

        if (string.IsNullOrEmpty(url))
        {
            ViewBag.Error = "URL is required.";
            return View("Preview");
        }

        Uri u;
        try { u = new Uri(url); }
        catch { return BadRequest("Invalid URL"); }

        if (!AllowedDomains.Contains(u.Host))
        {
            ViewBag.Error = "Domain not allowed.";
            return View("Preview");
        }

        try
        {
            var c = _httpFactory.CreateClient();
            c.Timeout = TimeSpan.FromSeconds(10);
            var r = await c.GetStringAsync(url);
            ViewBag.Url     = url;
            ViewBag.Content = r.Length > 5000 ? r[..5000] + "\n... (truncated)" : r;
        }
        catch (Exception ex)
        {
            ViewBag.Error = $"Failed to fetch URL: {ex.Message}";
        }

        return View("Preview");
    }

    [HttpGet]
    public IActionResult Preview()
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        return View();
    }

    [HttpPost]
    public async Task<IActionResult> Preview(string url)
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");

        if (string.IsNullOrEmpty(url))
        {
            ViewBag.Error = "URL is required.";
            return View();
        }

        if (url.Contains("localhost") || url.Contains("127.0.0.1") || url.Contains("::1"))
        {
            ViewBag.Error = "Access to internal addresses is not allowed.";
            return View();
        }

        try
        {
            var c = _httpFactory.CreateClient();
            c.Timeout = TimeSpan.FromSeconds(10);
            var r = await c.GetStringAsync(url);
            ViewBag.Url     = url;
            ViewBag.Content = r.Length > 5000 ? r[..5000] + "\n... (truncated)" : r;
        }
        catch (Exception ex)
        {
            ViewBag.Error = $"Failed to fetch URL: {ex.Message}";
        }

        return View();
    }
}
