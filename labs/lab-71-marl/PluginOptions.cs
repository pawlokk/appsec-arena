namespace Marl;

public class IntegrationOptions
{
    public bool   ExtendedReview  { get; set; } = false;
    public int    MaxPayloadKb    { get; set; } = 512;
    public string OutputFormat    { get; set; } = "json";
    public string ImportMode      { get; set; } = "strict";
}
