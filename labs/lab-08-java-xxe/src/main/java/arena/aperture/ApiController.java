package arena.aperture;

import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.w3c.dom.Document;
import org.w3c.dom.NodeList;

import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import java.io.ByteArrayInputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.ConcurrentHashMap;

@RestController
public class ApiController {

    private final ConcurrentHashMap<String, String> sessions = new ConcurrentHashMap<>();

    // Simulated document store
    private static final List<Map<String, Object>> DOCUMENTS = List.of(
        Map.of("id", "DOC-001", "title", "Q3 Budget Report", "owner", "finance", "status", "published"),
        Map.of("id", "DOC-002", "title", "Infrastructure Runbook", "owner", "ops", "status", "draft"),
        Map.of("id", "DOC-003", "title", "Onboarding Checklist", "owner", "hr", "status", "published"),
        Map.of("id", "DOC-004", "title", "API Integration Guide", "owner", "engineering", "status", "review")
    );

    // Simulated workflow tasks
    private static final List<Map<String, Object>> TASKS = List.of(
        Map.of("id", "TASK-101", "name", "Review budget approval", "assignee", "admin", "due", "2026-07-01"),
        Map.of("id", "TASK-102", "name", "Update DR procedure", "assignee", "ops", "due", "2026-06-20"),
        Map.of("id", "TASK-103", "name", "Complete security training", "assignee", "guest", "due", "2026-06-30")
    );

    private String loadAdminPassword() {
        try {
            String content = Files.readString(Paths.get("/app/secret.properties")).trim();
            if (!content.isBlank()) return content;
        } catch (Exception ignored) {
        }
        return null;
    }

    private String loadFlag() {
        try {
            return Files.readString(Paths.get("/app/flag_local.txt")).trim();
        } catch (Exception ignored) {
        }
        return null;
    }

    @GetMapping(value = "/", produces = MediaType.TEXT_HTML_VALUE)
    public String index() {
        return """
            <!DOCTYPE html>
            <html>
            <head>
                <title>Aperture — Document & Workflow Platform</title>
                <style>
                    body { background: #0d0f14; color: #c9d1d9; font-family: monospace; padding: 40px; max-width: 860px; }
                    h1 { color: #5c8aff; }
                    h2 { color: #8ab4ff; margin-top: 30px; }
                    code { background: #161b22; padding: 2px 6px; border-radius: 3px; color: #79c0ff; }
                    .endpoint { margin: 8px 0; }
                    .method { color: #3fb950; font-weight: bold; }
                    .path { color: #e3b341; }
                    p { color: #8b949e; }
                    .badge { display: inline-block; background: #21262d; border-radius: 3px;
                             padding: 1px 6px; font-size: 0.85em; color: #8ab4ff; margin-left: 6px; }
                </style>
            </head>
            <body>
                <h1>Aperture</h1>
                <p>Internal document management and workflow automation platform. Access is restricted to authorised users.</p>

                <h2>Available Endpoints</h2>

                <div class="endpoint">
                    <span class="method">POST</span> <span class="path">/api/login</span>
                    — Obtain a session token
                </div>
                <div class="endpoint">
                    <span class="method">GET</span> <span class="path">/api/profile</span>
                    — Current user profile <span class="badge">auth required</span>
                </div>
                <div class="endpoint">
                    <span class="method">GET</span> <span class="path">/api/documents</span>
                    — List available documents <span class="badge">auth required</span>
                </div>
                <div class="endpoint">
                    <span class="method">GET</span> <span class="path">/api/documents/{id}</span>
                    — Retrieve document metadata <span class="badge">auth required</span>
                </div>
                <div class="endpoint">
                    <span class="method">GET</span> <span class="path">/api/tasks</span>
                    — List workflow tasks assigned to current user <span class="badge">auth required</span>
                </div>
                <div class="endpoint">
                    <span class="method">GET</span> <span class="path">/api/health</span>
                    — Platform health check
                </div>
                <div class="endpoint">
                    <span class="method">GET</span> <span class="path">/api/admin/report</span>
                    — Administrative summary report <span class="badge">admin only</span>
                </div>

                <p style="margin-top:40px; font-size:0.85em;">Aperture v2.4.1 &mdash; &copy; 2026 Aperture Systems Ltd.</p>
            </body>
            </html>
            """;
    }

    @GetMapping("/api/health")
    public ResponseEntity<Map<String, Object>> health() {
        return ResponseEntity.ok(Map.of(
            "status", "UP",
            "timestamp", Instant.now().toString(),
            "service", "aperture-api",
            "version", "2.4.1"
        ));
    }

    /**
     * Parses an XML credential document submitted during login.
     * Uses the platform's standard XML processing pipeline which applies
     * schema validation and entity resolution required for SSO federation.
     */
    private static class XmlCredentialParser {

        record Credentials(String username, String password) {}

        Credentials parse(String xmlInput) throws MalformedInputException, ProcessingException {
            try {
                DocumentBuilderFactory factory = DocumentBuilderFactory.newInstance();
                factory.setNamespaceAware(true);
                DocumentBuilder builder = factory.newDocumentBuilder();
                builder.setErrorHandler(new org.xml.sax.helpers.DefaultHandler());

                Document doc = builder.parse(
                    new ByteArrayInputStream(xmlInput.getBytes(StandardCharsets.UTF_8))
                );

                NodeList usernameNodes = doc.getElementsByTagName("username");
                NodeList passwordNodes = doc.getElementsByTagName("password");

                if (usernameNodes.getLength() == 0 || passwordNodes.getLength() == 0) {
                    throw new MalformedInputException("Missing required credential fields");
                }

                return new Credentials(
                    usernameNodes.item(0).getTextContent(),
                    passwordNodes.item(0).getTextContent()
                );
            } catch (MalformedInputException e) {
                throw e;
            } catch (org.xml.sax.SAXParseException e) {
                throw new MalformedInputException("Credential document is not well-formed");
            } catch (Exception e) {
                throw new ProcessingException("Credential processing failed");
            }
        }

        static class MalformedInputException extends Exception {
            MalformedInputException(String msg) { super(msg); }
        }

        static class ProcessingException extends Exception {
            ProcessingException(String msg) { super(msg); }
        }
    }

    @PostMapping(value = "/api/login", consumes = {MediaType.APPLICATION_XML_VALUE, "text/xml", "*/*"})
    public ResponseEntity<Map<String, Object>> login(@RequestBody String xmlBody) {
        XmlCredentialParser parser = new XmlCredentialParser();
        XmlCredentialParser.Credentials creds;

        try {
            creds = parser.parse(xmlBody);
        } catch (XmlCredentialParser.MalformedInputException e) {
            return ResponseEntity.badRequest()
                .body(Map.of("error", e.getMessage()));
        } catch (XmlCredentialParser.ProcessingException e) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(Map.of("error", "Authentication service unavailable"));
        }

        String adminPassword = loadAdminPassword();

        if ("admin".equals(creds.username()) && adminPassword != null && adminPassword.equals(creds.password().trim())) {
            String token = UUID.randomUUID().toString();
            sessions.put(token, "admin");
            return ResponseEntity.ok(Map.of(
                "token", token,
                "role", "admin",
                "message", "Welcome back"
            ));
        } else if ("guest".equals(creds.username()) && "guest".equals(creds.password())) {
            String token = UUID.randomUUID().toString();
            sessions.put(token, "guest");
            return ResponseEntity.ok(Map.of(
                "token", token,
                "role", "guest",
                "message", "Logged in as guest"
            ));
        }

        return ResponseEntity.status(HttpStatus.UNAUTHORIZED)
            .body(Map.of("error", "Invalid credentials"));
    }

    @GetMapping("/api/profile")
    public ResponseEntity<Map<String, Object>> profile(
            @RequestHeader(value = "Authorization", required = false) String auth) {

        String role = resolveRole(auth);
        if (role == null) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).body(Map.of("error", "Unauthorized"));
        }

        return ResponseEntity.ok(Map.of(
            "username", "admin".equals(role) ? "admin" : "guest",
            "role", role,
            "status", "active",
            "lastLogin", "2026-06-11T08:14:32Z"
        ));
    }

    @GetMapping("/api/documents")
    public ResponseEntity<Object> listDocuments(
            @RequestHeader(value = "Authorization", required = false) String auth) {

        if (resolveRole(auth) == null) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).body(Map.of("error", "Unauthorized"));
        }

        return ResponseEntity.ok(Map.of("documents", DOCUMENTS, "total", DOCUMENTS.size()));
    }

    @GetMapping("/api/documents/{id}")
    public ResponseEntity<Object> getDocument(
            @PathVariable String id,
            @RequestHeader(value = "Authorization", required = false) String auth) {

        if (resolveRole(auth) == null) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).body(Map.of("error", "Unauthorized"));
        }

        return DOCUMENTS.stream()
            .filter(d -> id.equals(d.get("id")))
            .findFirst()
            .<ResponseEntity<Object>>map(ResponseEntity::ok)
            .orElseGet(() -> ResponseEntity.status(HttpStatus.NOT_FOUND).body(Map.of("error", "Document not found")));
    }

    @GetMapping("/api/tasks")
    public ResponseEntity<Object> listTasks(
            @RequestHeader(value = "Authorization", required = false) String auth) {

        String role = resolveRole(auth);
        if (role == null) {
            return ResponseEntity.status(HttpStatus.UNAUTHORIZED).body(Map.of("error", "Unauthorized"));
        }

        String username = "admin".equals(role) ? "admin" : "guest";
        List<Map<String, Object>> mine = TASKS.stream()
            .filter(t -> username.equals(t.get("assignee")) || "admin".equals(role))
            .toList();

        return ResponseEntity.ok(Map.of("tasks", mine, "total", mine.size()));
    }

    @GetMapping("/api/admin/report")
    public ResponseEntity<Object> adminReport(
            @RequestHeader(value = "Authorization", required = false) String auth) {

        if (!"admin".equals(resolveRole(auth))) {
            return ResponseEntity.status(HttpStatus.FORBIDDEN).body(Map.of("error", "Forbidden"));
        }

        String flag = loadFlag();
        if (flag == null) {
            return ResponseEntity.status(HttpStatus.INTERNAL_SERVER_ERROR)
                .body(Map.of("error", "Report generation failed"));
        }

        return ResponseEntity.ok(Map.of(
            "report", "Administrative summary — all systems nominal",
            "documents", DOCUMENTS.size(),
            "tasks", TASKS.size(),
            "users", 2,
            "token", flag
        ));
    }

    private String resolveRole(String authHeader) {
        if (authHeader == null || !authHeader.startsWith("Bearer ")) return null;
        String token = authHeader.substring(7).trim();
        return sessions.get(token);
    }
}
