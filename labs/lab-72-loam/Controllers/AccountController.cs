using System.Security.Cryptography;
using System.Text;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Data.Sqlite;

namespace Loam.Controllers;

public class AccountController : Controller
{
    private readonly LoamConfig _cfg;

    public AccountController(LoamConfig cfg) => _cfg = cfg;

    [HttpGet]
    public IActionResult Login() => View();

    [HttpPost]
    public IActionResult Login(string username, string password)
    {
        var h = Sh(password);
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        using var s = c.CreateCommand();
        s.CommandText = "SELECT Role FROM Users WHERE Username=@u AND PasswordHash=@p";
        s.Parameters.AddWithValue("@u", username);
        s.Parameters.AddWithValue("@p", h);
        var r = s.ExecuteScalar()?.ToString();

        if (r == null)
        {
            ViewBag.Error = "Invalid credentials.";
            return View();
        }

        HttpContext.Session.SetString("Username", username);
        HttpContext.Session.SetString("Role", r);
        return RedirectToAction("Index", "Home");
    }

    public IActionResult Logout()
    {
        HttpContext.Session.Clear();
        return RedirectToAction("Login");
    }

    private static string Sh(string a)
    {
        using var x = SHA256.Create();
        return Convert.ToHexString(x.ComputeHash(Encoding.UTF8.GetBytes(a))).ToLower();
    }
}
