namespace Loam.Models;

public class ImportPayload
{
    public string Data { get; set; } = "";
}

public class AppConfig
{
    public string? Name    { get; set; }
    public string? Version { get; set; }
    public string? Value   { get; set; }
}
