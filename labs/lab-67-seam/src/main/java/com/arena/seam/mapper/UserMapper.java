package com.arena.seam.mapper;

import com.arena.seam.User;
import com.arena.seam.dto.UserUpdateRequest;
import org.springframework.stereotype.Component;

@Component
public class UserMapper {

    public User applyUpdate(User user, UserUpdateRequest request) {
        if (request.getDisplayName() != null) {
            user.setDisplayName(request.getDisplayName());
        }
        if (request.getEmail() != null) {
            user.setEmail(request.getEmail());
        }
        if (request.getBio() != null) {
            user.setBio(request.getBio());
        }
        if (request.getOrganizationRole() != null) {
            user.setOrganizationRole(request.getOrganizationRole());
        }
        return user;
    }
}
