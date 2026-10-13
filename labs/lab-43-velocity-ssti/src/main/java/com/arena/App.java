package com.arena;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import org.apache.velocity.VelocityContext;
import org.apache.velocity.app.Velocity;
import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.util.Properties;
import java.util.stream.Collectors;

public class App {

    // Context helper bean for campaign templates
    public static class ToolBean {
        private String name = "CampaignTool";
        private String version = "1.0";

        public String getName() { return name; }
        public String getVersion() { return version; }
        public Class<?> resolve(String typeName) throws ClassNotFoundException {
            return Class.forName(typeName);
        }
    }

    // --- Servlets ---

    public static class LoginServlet extends HttpServlet {
        private static final String USER = System.getenv("ADMIN_USER") != null ? System.getenv("ADMIN_USER") : "admin";
        private static final String PASS;

        static {
            String adminPass = System.getenv("ADMIN_PASS");
            if (adminPass == null || adminPass.isEmpty()) {
                throw new IllegalStateException("ADMIN_PASS environment variable must be set");
            }
            PASS = adminPass;
        }

        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            resp.setContentType("text/html;charset=UTF-8");
            resp.getWriter().write(loginPage(""));
        }

        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            String username = req.getParameter("username");
            String password = req.getParameter("password");
            if (USER.equals(username) && PASS.equals(password)) {
                HttpSession session = req.getSession(true);
                session.setAttribute("user", username);
                resp.sendRedirect("/dashboard");
            } else {
                resp.setContentType("text/html;charset=UTF-8");
                resp.getWriter().write(loginPage("Invalid credentials."));
            }
        }

        private String loginPage(String error) {
            return "<!DOCTYPE html><html><head><title>Torque — Login</title>" +
                "<style>body{font-family:monospace;background:#0d0d0d;color:#00ff99;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}" +
                "form{background:#1a1a1a;padding:2rem;border:1px solid #00ff99;border-radius:4px;min-width:300px}" +
                "h2{margin-top:0}input{width:100%;padding:0.5rem;margin:0.4rem 0 1rem;background:#111;color:#fff;border:1px solid #444;border-radius:3px;box-sizing:border-box}" +
                "button{width:100%;padding:0.6rem;background:#00ff99;color:#000;border:none;border-radius:3px;cursor:pointer;font-weight:bold}" +
                ".err{color:#ff4444;margin-bottom:1rem}</style></head><body>" +
                "<form method='post' action='/login'><h2>Torque Campaign Platform</h2>" +
                (error.isEmpty() ? "" : "<div class='err'>" + error + "</div>") +
                "<label>Username</label><input name='username' type='text'/>" +
                "<label>Password</label><input name='password' type='password'/>" +
                "<button type='submit'>Login</button></form></body></html>";
        }
    }

    public static class DashboardServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            HttpSession session = req.getSession(false);
            if (session == null || session.getAttribute("user") == null) {
                resp.sendRedirect("/login");
                return;
            }
            resp.setContentType("text/html;charset=UTF-8");
            resp.getWriter().write(
                "<!DOCTYPE html><html><head><title>Torque — Dashboard</title>" +
                "<style>body{font-family:monospace;background:#0d0d0d;color:#00ff99;padding:2rem}" +
                "h1{border-bottom:1px solid #00ff99;padding-bottom:0.5rem}" +
                ".card{background:#1a1a1a;border:1px solid #333;padding:1rem;border-radius:4px;margin:1rem 0}" +
                "a{color:#00ccff}button{padding:0.5rem 1rem;background:#00ff99;color:#000;border:none;border-radius:3px;cursor:pointer;font-weight:bold}</style></head>" +
                "<body><h1>Torque Campaign Platform</h1>" +
                "<div class='card'><h3>Campaign Preview</h3>" +
                "<p>Render and proof-read your email campaign templates before sending.</p>" +
                "<p><a href='/preview-ui'>Open Template Editor</a></p></div>" +
                "</body></html>"
            );
        }
    }

    public static class PreviewUIServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            HttpSession session = req.getSession(false);
            if (session == null || session.getAttribute("user") == null) {
                resp.sendRedirect("/login");
                return;
            }
            resp.setContentType("text/html;charset=UTF-8");
            resp.getWriter().write(
                "<!DOCTYPE html><html><head><title>Torque — Template Editor</title>" +
                "<style>body{font-family:monospace;background:#0d0d0d;color:#00ff99;padding:2rem}" +
                "h1{border-bottom:1px solid #00ff99;padding-bottom:0.5rem}" +
                "textarea{width:100%;height:200px;background:#111;color:#fff;border:1px solid #444;border-radius:3px;padding:0.5rem;font-family:monospace;font-size:0.9rem;box-sizing:border-box}" +
                "button{padding:0.6rem 1.5rem;background:#00ff99;color:#000;border:none;border-radius:3px;cursor:pointer;font-weight:bold;margin-top:0.5rem}" +
                "pre{background:#1a1a1a;border:1px solid #333;padding:1rem;border-radius:4px;white-space:pre-wrap;word-break:break-all}" +
                "</style></head><body>" +
                "<h1>Campaign Template Editor</h1>" +
                "<p>Write your campaign template below and click Preview to render it.</p>" +
                "<textarea id='tmpl'>Hello $tool.name! Version: $tool.version</textarea><br>" +
                "<button onclick='preview()'>Preview</button>" +
                "<h3>Output:</h3><pre id='out'></pre>" +
                "<script>async function preview(){" +
                "const r=await fetch('/campaign/preview',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({template:document.getElementById('tmpl').value})});" +
                "const j=await r.json();document.getElementById('out').textContent=j.output||j.error;}</script>" +
                "</body></html>"
            );
        }
    }

    public static class CampaignPreviewServlet extends HttpServlet {
        private final Gson gson = new Gson();

        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            HttpSession session = req.getSession(false);
            if (session == null || session.getAttribute("user") == null) {
                resp.setStatus(401);
                resp.setContentType("application/json");
                resp.getWriter().write("{\"error\":\"Unauthorized\"}");
                return;
            }

            String body = req.getReader().lines().collect(Collectors.joining());
            JsonObject json;
            try {
                json = gson.fromJson(body, JsonObject.class);
            } catch (Exception e) {
                resp.setStatus(400);
                resp.setContentType("application/json");
                resp.getWriter().write("{\"error\":\"Invalid JSON\"}");
                return;
            }

            String template = json.has("template") ? json.get("template").getAsString() : "";

            VelocityContext context = new VelocityContext();
            context.put("tool", new ToolBean());

            StringWriter writer = new StringWriter();
            try {
                Velocity.evaluate(context, writer, "campaign-preview", template);
                String output = writer.toString();
                resp.setContentType("application/json");
                JsonObject result = new JsonObject();
                result.addProperty("output", output);
                resp.getWriter().write(gson.toJson(result));
            } catch (Exception e) {
                resp.setStatus(500);
                resp.setContentType("application/json");
                JsonObject err = new JsonObject();
                err.addProperty("error", e.getMessage());
                resp.getWriter().write(gson.toJson(err));
            }
        }
    }

    // --- Main ---

    public static void main(String[] args) throws Exception {
        Properties props = new Properties();
        props.setProperty("runtime.log.logsystem.class", "org.apache.velocity.runtime.log.NullLogChute");
        Velocity.init(props);

        Server server = new Server(8080);
        ServletContextHandler handler = new ServletContextHandler(ServletContextHandler.SESSIONS);
        handler.setContextPath("/");

        handler.addServlet(new ServletHolder(new LoginServlet()), "/login");
        handler.addServlet(new ServletHolder(new DashboardServlet()), "/dashboard");
        handler.addServlet(new ServletHolder(new PreviewUIServlet()), "/preview-ui");
        handler.addServlet(new ServletHolder(new CampaignPreviewServlet()), "/campaign/preview");

        handler.addServlet(new ServletHolder(new HttpServlet() {
            @Override
            protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
                resp.sendRedirect("/login");
            }
        }), "/");

        server.setHandler(handler);
        server.start();
        System.out.println("[Torque] Server started on port 8080");
        server.join();
    }
}
