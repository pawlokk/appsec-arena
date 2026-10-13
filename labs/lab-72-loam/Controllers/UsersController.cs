using System.Collections.Generic;
using Loam.Models;
using Microsoft.AspNetCore.Mvc;
using Microsoft.Data.Sqlite;

namespace Loam.Controllers;

public class UsersController : Controller
{
    private readonly LoamConfig _cfg;

    public UsersController(LoamConfig cfg) => _cfg = cfg;

    [ValidateInput(true)]
    public IActionResult Search(string? q)
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");

        var r = new List<User>();

        if (!string.IsNullOrEmpty(q))
        {
            using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
            c.Open();
            using var s = c.CreateCommand();
            string b = "SELECT Id, Username, PasswordHash, Role, Department, Email FROM Users WHERE Username LIKE '%" + q + "%'";
            s.CommandText = b;

            using var x = s.ExecuteReader();
            while (x.Read())
            {
                r.Add(new User
                {
                    Id           = x.GetInt32(0),
                    Username     = x.GetString(1),
                    PasswordHash = x.GetString(2),
                    Role         = x.GetString(3),
                    Department   = x.GetString(4),
                    Email        = x.GetString(5),
                });
            }
        }

        ViewBag.Query   = q;
        ViewBag.Results = r;
        return View();
    }

    [HttpGet]
    [ValidateInput(true)]
    public IActionResult DirectorySearch(string? q)
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");

        var r = new List<User>();

        if (!string.IsNullOrEmpty(q))
        {
            using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
            c.Open();
            using var s = c.CreateCommand();
            s.CommandText = "SELECT Id, Username, PasswordHash, Role, Department, Email FROM Users WHERE Username LIKE @q";
            s.Parameters.AddWithValue("@q", $"%{q}%");

            using var x = s.ExecuteReader();
            while (x.Read())
            {
                r.Add(new User
                {
                    Id           = x.GetInt32(0),
                    Username     = x.GetString(1),
                    PasswordHash = x.GetString(2),
                    Role         = x.GetString(3),
                    Department   = x.GetString(4),
                    Email        = x.GetString(5),
                });
            }
        }

        ViewBag.Query   = q;
        ViewBag.Results = r;
        return View("Search", r);
    }

    private const bool ENABLE_ARCHIVE_SEARCH = false;
    public IActionResult ArchiveSearch(string filter)
    {
        if (!ENABLE_ARCHIVE_SEARCH) return NotFound();
        var r = new List<User>();
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        using var s = c.CreateCommand();
        var b = $"SELECT Id, Username, PasswordHash, Role, Department, Email FROM Users WHERE Department = '{filter}'";
        s.CommandText = b;
        using var x = s.ExecuteReader();
        while (x.Read())
        {
            r.Add(new User
            {
                Id           = x.GetInt32(0),
                Username     = x.GetString(1),
                PasswordHash = x.GetString(2),
                Role         = x.GetString(3),
                Department   = x.GetString(4),
                Email        = x.GetString(5),
            });
        }
        return View(r);
    }

    public IActionResult Directory()
    {
        if (HttpContext.Session.GetString("Username") == null)
            return RedirectToAction("Login", "Account");

        var r = new List<User>();
        using var c = new SqliteConnection($"Data Source={_cfg.DbPath}");
        c.Open();
        using var s = c.CreateCommand();
        s.CommandText = "SELECT Id, Username, PasswordHash, Role, Department, Email FROM Users";
        using var x = s.ExecuteReader();
        while (x.Read())
        {
            r.Add(new User
            {
                Id           = x.GetInt32(0),
                Username     = x.GetString(1),
                PasswordHash = x.GetString(2),
                Role         = x.GetString(3),
                Department   = x.GetString(4),
                Email        = x.GetString(5),
            });
        }

        ViewBag.Users = r;
        return View();
    }
}
