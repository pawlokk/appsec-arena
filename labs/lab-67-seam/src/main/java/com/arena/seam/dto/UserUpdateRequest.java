package com.arena.seam.dto;

public class UserUpdateRequest {

    private String displayName;
    private String email;
    private String bio;

    private String organizationRole;

    public String getDisplayName() { return displayName; }
    public void setDisplayName(String displayName) { this.displayName = displayName; }

    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }

    public String getBio() { return bio; }
    public void setBio(String bio) { this.bio = bio; }

    public String getOrganizationRole() { return organizationRole; }
    public void setOrganizationRole(String organizationRole) { this.organizationRole = organizationRole; }
}
