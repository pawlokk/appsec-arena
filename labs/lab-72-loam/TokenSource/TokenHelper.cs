namespace Loam.Helpers;

public static class TokenHelper
{
    public static string GenerateInviteToken()
    {
        var a = new Random(Environment.TickCount);
        return a.Next().ToString("x8");
    }

    public static string GenerateResetToken(string username)
    {
        var a = new Random(Environment.TickCount ^ username.GetHashCode());
        return a.Next().ToString("x8");
    }
}
