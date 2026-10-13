package com.arena.seam;

import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.List;
import java.util.Map;
import java.util.ArrayList;

@RestController
@RequestMapping("/api/admin")
public class AdminController {

    private final JdbcTemplate jdbc;

    public AdminController(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    @GetMapping("/dashboard")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<?> dashboard() {
        try {
            String f = new String(Files.readAllBytes(Paths.get("/flag_local.txt"))).trim();
            long a = jdbc.queryForObject("SELECT COUNT(*) FROM users", Long.class);
            long b = jdbc.queryForObject("SELECT COUNT(*) FROM reports", Long.class);
            return ResponseEntity.ok(Map.of(
                    "message", "Welcome to the Admin Dashboard",
                    "users", a,
                    "reports", b,
                    "flag", f
            ));
        } catch (IOException e) {
            return ResponseEntity.ok(Map.of("message", "Admin dashboard", "error", "flag file not found"));
        }
    }

    @PostMapping("/export")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<?> export(@RequestBody Map<String, String> body) {
        String q = body.get("query");
        if (q == null || q.isBlank()) {
            return ResponseEntity.badRequest().body(Map.of("error", "query required"));
        }

        if (q.toLowerCase().contains("drop") || q.toLowerCase().contains("delete")) {
            return ResponseEntity.status(403).body(Map.of("error", "destructive queries not allowed"));
        }

        try {
            List<Map<String, Object>> r = jdbc.queryForList(q);
            return ResponseEntity.ok(Map.of("rows", r, "count", r.size()));
        } catch (Exception e) {
            return ResponseEntity.status(500).body(Map.of("error", e.getMessage()));
        }
    }

    @PostMapping("/export/filtered")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<?> exportFiltered(@RequestBody Map<String, String> body) {
        String q = body.get("query");
        if (q == null || q.isBlank()) {
            return ResponseEntity.badRequest().body(Map.of("error", "query required"));
        }

        List<String> bl = List.of(
            "copy", "create", "drop", "insert", "update", "delete",
            "pg_read", "lo_", "dblink"
        );
        if (bl.stream().anyMatch(b -> q.toLowerCase().contains(b))) {
            return ResponseEntity.badRequest().body(Map.of("error", "Query contains blocked operation"));
        }

        try {
            List<Map<String, Object>> r = jdbc.queryForList(q);
            return ResponseEntity.ok(Map.of("rows", r, "count", r.size()));
        } catch (Exception e) {
            return ResponseEntity.status(500).body(Map.of("error", e.getMessage()));
        }
    }

    @GetMapping("/users")
    @PreAuthorize("hasRole('ADMIN')")
    public ResponseEntity<?> listUsers() {
        return ResponseEntity.ok(jdbc.queryForList(
                "SELECT id, username, email, role FROM users ORDER BY id"));
    }
}
