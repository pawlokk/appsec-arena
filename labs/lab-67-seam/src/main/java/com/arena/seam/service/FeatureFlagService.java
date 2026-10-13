package com.arena.seam.service;

import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;

import java.util.Map;

@Service
public class FeatureFlagService {

    private final JdbcTemplate jdbc;

    public FeatureFlagService(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    public boolean isEnabled(Long userId, String flag) {
        try {
            Boolean result = jdbc.queryForObject(
                "SELECT (feature_flags->>?)::boolean FROM users WHERE id = ?",
                Boolean.class,
                flag, userId
            );
            return Boolean.TRUE.equals(result);
        } catch (Exception e) {
            return false;
        }
    }

    public Map<String, Object> getFlags(Long userId) {
        try {
            String json = jdbc.queryForObject(
                "SELECT feature_flags::text FROM users WHERE id = ?",
                String.class, userId
            );
            return json != null ? Map.of("raw", json) : Map.of();
        } catch (Exception e) {
            return Map.of();
        }
    }
}
