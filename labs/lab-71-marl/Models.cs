
using System;

namespace Marl.Models
{
    public class UserSession
    {
        public string Username { get; set; } = "";
        public string Role { get; set; } = "user";
        public DateTime IssuedAt { get; set; } = DateTime.UtcNow;
    }

    public class User
    {
        public int Id { get; set; }
        public string Username { get; set; } = "";
        public string PasswordHash { get; set; } = "";
        public string Role { get; set; } = "user";
    }

    public class ConfigImportDto
    {
        public string? Name    { get; set; }
        public string? Version { get; set; }
        public string? Value   { get; set; }
    }
}
