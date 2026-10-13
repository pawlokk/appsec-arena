using Microsoft.AspNetCore.Mvc;

namespace Loam.Controllers;

public class HomeController : Controller
{
    public IActionResult Index()
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");
        return View();
    }
}
