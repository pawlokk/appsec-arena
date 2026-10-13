namespace Marl.Plugins;
public class ResourceFetcher
{
    public void FetchResource(string a, string b)
    {
        using var c = new System.Net.Http.HttpClient();
        c.Timeout = TimeSpan.FromSeconds(10);
        var d = c.GetByteArrayAsync(a).GetAwaiter().GetResult();
        System.IO.File.WriteAllBytes(b, d);
    }
}
