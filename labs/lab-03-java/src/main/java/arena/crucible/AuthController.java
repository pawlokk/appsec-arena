package arena.crucible;

import io.jsonwebtoken.*;
import io.jsonwebtoken.security.Keys;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;

import javax.crypto.SecretKey;
import java.nio.charset.StandardCharsets;
import java.util.*;

/**
 * Crucible — authentication and admin API
 */
@RestController
public class AuthController {

    // Signing key
    private static final String JWT_SECRET = "crucible";
    private static final SecretKey KEY = Keys.hmacShaKeyFor(
        JWT_SECRET.getBytes(StandardCharsets.UTF_8)
    );

    @Value("${arena.flag.local:ARENA{LOCAL_dev}}")
    private String flagLocal;

    // ── Login ─────────────────────────────────────────────────────────────────
    @PostMapping("/api/login")
    public ResponseEntity<?> login(@RequestBody Map<String, String> body) {
        String username = body.getOrDefault("username", "");
        String password = body.getOrDefault("password", "");

        // Hardcoded credentials for demo
        Map<String, String[]> users = Map.of(
            "alice", new String[]{"alicepass", "user"},
            "bob",   new String[]{"bobpass",   "user"}
        );

        String[] entry = users.get(username);
        if (entry == null || !entry[0].equals(password)) {
            return ResponseEntity.status(401).body(Map.of("error", "Invalid credentials"));
        }

        String token = Jwts.builder()
            .setSubject(username)
            .claim("role", entry[1])
            .signWith(KEY, SignatureAlgorithm.HS256)
            .compact();

        return ResponseEntity.ok(Map.of("token", token));
    }

    // ── Profile ───────────────────────────────────────────────────────────────
    @GetMapping("/api/profile")
    public ResponseEntity<?> profile(@RequestHeader("Authorization") String auth) {
        Claims claims = parseToken(auth);
        if (claims == null) return ResponseEntity.status(401).body(Map.of("error", "Unauthorised"));
        return ResponseEntity.ok(Map.of("sub", claims.getSubject(), "role", claims.get("role")));
    }

    // ── Admin flag ────────────────────────────────────────────────────────────
    @GetMapping("/api/admin/flag")
    public ResponseEntity<?> adminFlag(@RequestHeader("Authorization") String auth) {
        Claims claims = parseToken(auth);
        if (claims == null) return ResponseEntity.status(401).body(Map.of("error", "Unauthorised"));
        if (!"admin".equals(claims.get("role"))) {
            return ResponseEntity.status(403).body(Map.of("error", "Admin only"));
        }
        return ResponseEntity.ok(Map.of("flag", flagLocal));
    }

    // ── Object processing ─────────────────────────────────────────────────────
    @PostMapping("/api/admin/run")
    public ResponseEntity<?> adminRun(
            @RequestHeader("Authorization") String auth,
            @RequestBody Map<String, String> body) throws Exception {

        Claims claims = parseToken(auth);
        if (claims == null) return ResponseEntity.status(401).body(Map.of("error", "Unauthorised"));
        if (!"admin".equals(claims.get("role"))) {
            return ResponseEntity.status(403).body(Map.of("error", "Admin only"));
        }

        // Decode and process the submitted object
        String b64 = body.getOrDefault("data", "");
        byte[] bytes = Base64.getDecoder().decode(b64);
        try (java.io.ObjectInputStream ois =
                new java.io.ObjectInputStream(new java.io.ByteArrayInputStream(bytes))) {
            Object obj = ois.readObject();
            return ResponseEntity.ok(Map.of("result", obj.toString()));
        } catch (Exception e) {
            return ResponseEntity.status(500).body(Map.of("error", e.getMessage()));
        }
    }

    // ── Index ─────────────────────────────────────────────────────────────────
    @GetMapping("/")
    public ResponseEntity<String> index() {
        String html = """
            <!DOCTYPE html>
            <html lang="en">
            <head><meta charset="UTF-8"><title>Crucible</title>
            <script>window.__ARENA_LAB__ = {
              id: 'lab-03-java', name: 'Crucible', level: 1,
              tags: ['JWT', 'Auth Bypass', 'Deserialization', 'RCE'],
              platform: 'http://localhost:8080'
            };</script>
            <style>
              body{font-family:monospace;background:#0d0f14;color:#d4d8e8;margin:0;padding:2rem}
              h1{color:#5c8aff}pre{background:#1a1e2a;padding:1rem;border-radius:8px;overflow:auto}
            </style></head>
            <body>
            <h1>Crucible</h1>
            <p>Internal build system API. JWT-authenticated.</p>
            <pre>POST /api/login          { username, password }
            GET  /api/profile         Authorization: Bearer &lt;token&gt;
            GET  /api/admin/flag      admin role required
            POST /api/admin/run       admin only — deserializes base64 Java object</pre>
            <p>Users: alice / alicepass &nbsp; bob / bobpass</p>
            </body></html>
            """;
        return ResponseEntity.ok().contentType(MediaType.TEXT_HTML).body(html);
    }

    // ── Helper ────────────────────────────────────────────────────────────────
    private Claims parseToken(String authHeader) {
        try {
            String token = authHeader.replace("Bearer ", "").trim();
            return Jwts.parserBuilder().setSigningKey(KEY).build()
                       .parseClaimsJws(token).getBody();
        } catch (Exception e) {
            return null;
        }
    }
}
