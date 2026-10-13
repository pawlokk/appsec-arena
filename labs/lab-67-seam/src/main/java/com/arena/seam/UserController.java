package com.arena.seam;

import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.core.Authentication;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@RestController
@RequestMapping("/api")
public class UserController {

    private final JdbcTemplate jdbc;
    private final PasswordEncoder passwordEncoder;
    private final JwtUtil jwtUtil;

    public UserController(JdbcTemplate jdbc, PasswordEncoder passwordEncoder, JwtUtil jwtUtil) {
        this.jdbc = jdbc;
        this.passwordEncoder = passwordEncoder;
        this.jwtUtil = jwtUtil;
    }

    @PostMapping("/register")
    public ResponseEntity<?> register(@RequestBody User u) {
        if (u.getUsername() == null || u.getPassword() == null || u.getEmail() == null) {
            return ResponseEntity.badRequest().body(Map.of("error", "username, email, and password required"));
        }
        String x = u.getRole() != null ? u.getRole() : "user";
        String h = passwordEncoder.encode(u.getPassword());
        try {
            jdbc.update("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)",
                    u.getUsername(), u.getEmail(), h, x);
            return ResponseEntity.ok(Map.of("message", "registered", "username", u.getUsername()));
        } catch (Exception e) {
            return ResponseEntity.badRequest().body(Map.of("error", "registration failed: " + e.getMessage()));
        }
    }

    @PostMapping("/v2/register")
    public ResponseEntity<?> registerV2(@RequestBody RegisterDto b) {
        if (b.getUsername() == null || b.getPassword() == null || b.getEmail() == null) {
            return ResponseEntity.badRequest().body(Map.of("error", "username, email, and password required"));
        }
        String h = passwordEncoder.encode(b.getPassword());
        try {
            jdbc.update("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)",
                    b.getUsername(), b.getEmail(), h, "user");
            return ResponseEntity.ok(Map.of("message", "registered", "username", b.getUsername()));
        } catch (Exception e) {
            return ResponseEntity.badRequest().body(Map.of("error", "registration failed: " + e.getMessage()));
        }
    }

    @PostMapping("/login")
    public ResponseEntity<?> login(@RequestBody Map<String, String> body) {
        String n = body.get("username");
        String p = body.get("password");
        if (n == null || p == null) {
            return ResponseEntity.badRequest().body(Map.of("error", "username and password required"));
        }
        try {
            Map<String, Object> r = jdbc.queryForMap("SELECT id, password, role FROM users WHERE username = ?", n);
            String h = (String) r.get("password");
            if (!passwordEncoder.matches(p, h)) {
                return ResponseEntity.status(401).body(Map.of("error", "invalid credentials"));
            }
            String x = (String) r.get("role");
            String t = jwtUtil.generateToken(n, x);
            return ResponseEntity.ok(Map.of("token", t, "role", x));
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "invalid credentials"));
        }
    }

    @GetMapping("/profile")
    public ResponseEntity<?> profile(Authentication auth) {
        String n = auth.getName();
        try {
            return ResponseEntity.ok(jdbc.queryForMap(
                    "SELECT id, username, email, role FROM users WHERE username = ?", n));
        } catch (Exception e) {
            return ResponseEntity.status(404).body(Map.of("error", "user not found"));
        }
    }
}
