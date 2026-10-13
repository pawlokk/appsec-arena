package com.arena.seam;

import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.core.Authentication;
import org.springframework.validation.annotation.Validated;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/reports")
@Validated
public class ReportController {

    private final JdbcTemplate jdbc;

    public ReportController(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    @GetMapping
    public ResponseEntity<?> listReports(
            @RequestParam(required = false) String category,
            Authentication auth) {

        String n = auth.getName();
        Long i;
        try {
            i = jdbc.queryForObject("SELECT id FROM users WHERE username = ?", Long.class, n);
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "user not found"));
        }

        List<Map<String, Object>> r;
        if (category != null && !category.isEmpty()) {
            String q = "SELECT * FROM reports WHERE category = '" + category + "' AND owner_id = " + i;
            r = jdbc.queryForList(q);
        } else {
            r = jdbc.queryForList("SELECT * FROM reports WHERE owner_id = ?", i);
        }

        return ResponseEntity.ok(r);
    }

    @GetMapping("/search")
    public ResponseEntity<?> searchReports(
            @RequestParam(required = false, defaultValue = "") String title,
            Authentication auth) {

        String n = auth.getName();
        Long i;
        try {
            i = jdbc.queryForObject("SELECT id FROM users WHERE username = ?", Long.class, n);
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "user not found"));
        }

        return ResponseEntity.ok(jdbc.queryForList(
            "SELECT * FROM reports WHERE title LIKE ? AND owner_id = ?", "%" + title + "%", i));
    }

    @PostMapping
    public ResponseEntity<?> createReport(@RequestBody Map<String, String> body, Authentication auth) {
        String n = auth.getName();
        Long i;
        try {
            i = jdbc.queryForObject("SELECT id FROM users WHERE username = ?", Long.class, n);
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "user not found"));
        }
        String t = body.get("title");
        String ct = body.get("category");
        String o = body.get("content");
        if (t == null || ct == null) {
            return ResponseEntity.badRequest().body(Map.of("error", "title and category required"));
        }
        jdbc.update("INSERT INTO reports (title, category, content, owner_id) VALUES (?, ?, ?, ?)",
                t, ct, o, i);
        return ResponseEntity.ok(Map.of("message", "report created"));
    }
}
