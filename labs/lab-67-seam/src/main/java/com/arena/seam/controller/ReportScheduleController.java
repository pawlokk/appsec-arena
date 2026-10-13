package com.arena.seam.controller;

import com.arena.seam.service.ReportScheduler;
import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;
import java.util.Set;

@RestController
@RequestMapping("/api/reports/schedules")
public class ReportScheduleController {

    private static final Set<String> VALID_SORT_FIELDS = Set.of(
        "created_at", "title", "category", "id"
    );

    private final JdbcTemplate jdbc;
    private final ReportScheduler scheduler;

    public ReportScheduleController(JdbcTemplate jdbc, ReportScheduler scheduler) {
        this.jdbc = jdbc;
        this.scheduler = scheduler;
    }

    @PostMapping
    public ResponseEntity<?> createSchedule(
            @RequestBody Map<String, Object> body,
            Authentication auth) {

        String username = auth.getName();
        Long userId;
        try {
            userId = jdbc.queryForObject("SELECT id FROM users WHERE username = ?", Long.class, username);
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "user not found"));
        }

        String sortPreference = (String) body.getOrDefault("sort_preference", "created_at");
        Long tenantId = body.get("tenant_id") instanceof Number n ? n.longValue() : 1L;

        if (!VALID_SORT_FIELDS.contains(sortPreference)) {
            return ResponseEntity.badRequest().body(Map.of(
                "error", "invalid sort_preference",
                "allowed", VALID_SORT_FIELDS
            ));
        }

        jdbc.update(
            "INSERT INTO report_schedules (tenant_id, sort_preference, status, created_by) VALUES (?, ?, 'pending', ?)",
            tenantId, sortPreference, userId
        );

        return ResponseEntity.ok(Map.of("status", "scheduled", "sort_preference", sortPreference));
    }

    @GetMapping
    public ResponseEntity<?> listSchedules(Authentication auth) {
        var schedules = jdbc.queryForList(
            "SELECT * FROM report_schedules ORDER BY created_at DESC LIMIT 20"
        );
        return ResponseEntity.ok(schedules);
    }

    @PostMapping("/trigger")
    public ResponseEntity<?> triggerScheduler(Authentication auth) {
        String username = auth.getName();
        boolean isAdmin;
        try {
            String role = jdbc.queryForObject(
                "SELECT role FROM users WHERE username = ?", String.class, username
            );
            isAdmin = "admin".equals(role);
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "user not found"));
        }

        if (!isAdmin) {
            return ResponseEntity.status(403).body(Map.of("error", "admin required"));
        }

        scheduler.processScheduledReports();
        return ResponseEntity.ok(Map.of("status", "triggered"));
    }
}
