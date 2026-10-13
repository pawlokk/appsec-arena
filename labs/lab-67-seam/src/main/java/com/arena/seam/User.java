package com.arena.seam;

public class User {
    private Long id;
    private String username;
    private String email;
    private String password;
    private String role = "user";
    private String displayName;
    private String bio;
    private Long tenantId;
    private String organizationRole = "member";

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }

    public String getUsername() { return username; }
    public void setUsername(String username) { this.username = username; }

    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }

    public String getPassword() { return password; }
    public void setPassword(String password) { this.password = password; }

    public String getRole() { return role; }
    public void setRole(String role) { this.role = role; }

    public String getDisplayName() { return displayName; }
    public void setDisplayName(String displayName) { this.displayName = displayName; }

    public String getBio() { return bio; }
    public void setBio(String bio) { this.bio = bio; }

    public Long getTenantId() { return tenantId; }
    public void setTenantId(Long tenantId) { this.tenantId = tenantId; }

    public String getOrganizationRole() { return organizationRole; }
    public void setOrganizationRole(String organizationRole) { this.organizationRole = organizationRole; }
}
