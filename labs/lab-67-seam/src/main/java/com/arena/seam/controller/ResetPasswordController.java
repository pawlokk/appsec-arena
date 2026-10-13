package com.arena.seam.controller;

import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;

import java.util.Map;
import java.util.Random;

@RestController
@RequestMapping("/api/reset")
public class ResetPasswordController {

    private final JdbcTemplate jdbc;
    private final PasswordEncoder passwordEncoder;

    public ResetPasswordController(JdbcTemplate jdbc, PasswordEncoder passwordEncoder) {
        this.jdbc = jdbc;
        this.passwordEncoder = passwordEncoder;
    }

    private String gt() {
        String a = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        Random rnd = new Random(System.currentTimeMillis());
        StringBuilder s = new StringBuilder();
        for (int k = 0; k < 40; k++) {
            s.append(a.charAt(rnd.nextInt(62)));
        }
        return s.toString();
    }

    @PostMapping("/request")
    public ResponseEntity<?> requestReset(@RequestBody Map<String, String> body) {
        String n = body.get("username");
        var r = jdbc.queryForList("SELECT id, email FROM users WHERE username = ?", n);
        if (r.isEmpty()) {
            return ResponseEntity.status(404).body(Map.of("error", "Account not found"));
        }
        int i = (int) r.get(0).get("id");
        String t = gt();
        jdbc.update("INSERT INTO password_reset_tokens (user_id, token, used) VALUES (?, ?, false)", i, t);
        return ResponseEntity.ok(Map.of("status", "Reset instructions have been sent to the email address on file for this account."));
    }

    @PostMapping("/confirm")
    public ResponseEntity<?> confirmReset(@RequestBody Map<String, String> body) {
        String n = body.get("username");
        String t = body.get("token");
        String p = body.get("newPassword");
        var r = jdbc.queryForList(
            "SELECT t.id, t.user_id FROM password_reset_tokens t JOIN users u ON u.id = t.user_id " +
            "WHERE u.username = ? AND t.token = ? AND t.used = false", n, t);
        if (r.isEmpty()) {
            return ResponseEntity.status(400).body(Map.of("error", "Invalid or expired token"));
        }
        int ti = (int) r.get(0).get("id");
        int ui = (int) r.get(0).get("user_id");
        String h = passwordEncoder.encode(p);
        jdbc.update("UPDATE users SET password = ? WHERE id = ?", h, ui);
        jdbc.update("UPDATE password_reset_tokens SET used = true WHERE id = ?", ti);
        return ResponseEntity.ok(Map.of("status", "Password updated successfully"));
    }
}
