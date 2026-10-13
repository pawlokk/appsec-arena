namespace Loam.Models;

public class WebhookModel
{
    public int    Id          { get; set; }
    public int    OwnerId     { get; set; }
    public string TenantId    { get; set; } = "default";
    public string Url         { get; set; } = "";
    public string EventType   { get; set; } = "generic";
    public string PayloadJson { get; set; } = "{}";
    public bool   Active      { get; set; } = true;
}
