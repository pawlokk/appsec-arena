package com.arena.seam.service;

import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.Map;

@Service
public class TenantService {

    private final JdbcTemplate jdbc;

    public TenantService(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    public Long getTenantForUser(Long userId) {
        try {
            return jdbc.queryForObject(
                "SELECT tenant_id FROM users WHERE id = ?",
                Long.class, userId
            );
        } catch (Exception e) {
            return null;
        }
    }

    public boolean userBelongsToTenant(Long userId, Long tenantId) {
        Integer count = jdbc.queryForObject(
            "SELECT COUNT(*) FROM users WHERE id = ? AND tenant_id = ?",
            Integer.class, userId, tenantId
        );
        return count != null && count > 0;
    }

    public List<Map<String, Object>> getTenantsForAdmin() {
        return jdbc.queryForList("SELECT * FROM tenants ORDER BY name");
    }
}
