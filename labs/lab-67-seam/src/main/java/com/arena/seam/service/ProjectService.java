package com.arena.seam.service;

import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;

import java.util.List;
import java.util.Map;

@Service
public class ProjectService {

    private final JdbcTemplate jdbc;

    public ProjectService(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    public Map<String, Object> getProjectById(Long projectId) {
        try {
            return jdbc.queryForMap(
                "SELECT id, name, description, owner_id, tenant_id FROM projects WHERE id = ?",
                projectId
            );
        } catch (Exception e) {
            return null;
        }
    }

    public List<Map<String, Object>> getProjectsByTenant(Long tenantId) {
        return jdbc.queryForList(
            "SELECT id, name, description, owner_id FROM projects WHERE tenant_id = ? ORDER BY id",
            tenantId
        );
    }

    public List<Map<String, Object>> getReportsForProject(Long projectId) {
        return jdbc.queryForList(
            "SELECT id, title, status FROM reports WHERE project_id = ? ORDER BY id",
            projectId
        );
    }
}
