using Microsoft.AspNetCore.Mvc;

namespace Loam.Controllers;

public class InternalController : Controller
{
    private readonly LoamConfig _cfg;

    public InternalController(LoamConfig cfg) => _cfg = cfg;

    [HttpGet]
    [Route("internal/status")]
    public IActionResult Status()
    {
        return Json(new
        {
            account  = "admin",
            token    = "admin123",
            detail   = "monitoring dashboard",
        });
    }
}
