package com.arena;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import freemarker.template.Configuration;
import freemarker.template.Template;
import freemarker.template.TemplateExceptionHandler;
import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.IOException;
import java.io.PrintWriter;
import java.io.StringWriter;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.util.HashMap;
import java.util.Map;

public class App {

    static final String ADMIN_USER = System.getenv("ADMIN_USER") != null ? System.getenv("ADMIN_USER") : "admin";
    static final String ADMIN_PASS;
    static {
        String p = System.getenv("ADMIN_PASS");
        if (p == null || p.isEmpty()) {
            throw new IllegalStateException("ADMIN_PASS environment variable must be set");
        }
        ADMIN_PASS = p;
    }

    static final Configuration FM_CFG;

    static {
        FM_CFG = new Configuration(Configuration.VERSION_2_3_31);
        FM_CFG.setTemplateExceptionHandler(TemplateExceptionHandler.RETHROW_HANDLER);
        FM_CFG.setLogTemplateExceptions(false);
    }

    public static void main(String[] args) throws Exception {
        Server server = new Server(8080);

        ServletContextHandler ctx = new ServletContextHandler(ServletContextHandler.SESSIONS);
        ctx.setContextPath("/");

        ctx.addServlet(new ServletHolder(new LoginServlet()), "/login");
        ctx.addServlet(new ServletHolder(new DashboardServlet()), "/dashboard");
        ctx.addServlet(new ServletHolder(new ReportServlet()), "/report");
        ctx.addServlet(new ServletHolder(new IndexServlet()), "/");

        server.setHandler(ctx);
        server.start();
        System.out.println("Furnace started on port 8080");
        server.join();
    }

    // ── Index ──────────────────────────────────────────────────────────────────
    public static class IndexServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            resp.setContentType("text/html;charset=UTF-8");
            resp.getWriter().println("""
                    <!DOCTYPE html>
                    <html>
                    <head><title>Furnace - Report Generator</title>
                    <style>
                      body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;margin:40px auto;max-width:700px;}
                      h1{color:#e94560;} a{color:#0f3460;background:#e94560;padding:6px 14px;text-decoration:none;border-radius:4px;}
                      p{color:#aaa;}
                    </style>
                    </head>
                    <body>
                    <h1>Furnace</h1>
                    <p>Internal report generator. Login to access the dashboard.</p>
                    <br/><a href="/login">Login</a>
                    </body></html>
                    """);
        }
    }

    // ── Login ──────────────────────────────────────────────────────────────────
    public static class LoginServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            resp.setContentType("text/html;charset=UTF-8");
            resp.getWriter().println("""
                    <!DOCTYPE html>
                    <html>
                    <head><title>Furnace - Login</title>
                    <style>
                      body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;margin:40px auto;max-width:400px;}
                      h1{color:#e94560;}
                      input{width:100%;padding:8px;margin:6px 0;background:#16213e;color:#e0e0e0;border:1px solid #0f3460;box-sizing:border-box;}
                      button{background:#e94560;color:#fff;border:none;padding:10px 20px;cursor:pointer;width:100%;}
                      .err{color:#ff6b6b;}
                    </style>
                    </head>
                    <body>
                    <h1>Login</h1>
                    <form method="POST" action="/login">
                      <input name="user" placeholder="Username" /><br/>
                      <input name="pass" type="password" placeholder="Password" /><br/>
                      <button type="submit">Login</button>
                    </form>
                    </body></html>
                    """);
        }

        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            String user = null;
            String pass = null;

            String ct = req.getContentType();
            if (ct != null && ct.contains("application/json")) {
                ObjectMapper om = new ObjectMapper();
                JsonNode body = om.readTree(req.getInputStream());
                user = body.path("user").asText();
                pass = body.path("pass").asText();
            } else {
                user = req.getParameter("user");
                pass = req.getParameter("pass");
            }

            if (ADMIN_USER.equals(user) && ADMIN_PASS.equals(pass)) {
                HttpSession session = req.getSession(true);
                session.setAttribute("authenticated", true);
                session.setAttribute("username", user);
                resp.sendRedirect("/dashboard");
            } else {
                resp.setStatus(401);
                resp.setContentType("text/html;charset=UTF-8");
                resp.getWriter().println("<p style='color:red'>Invalid credentials</p>");
            }
        }
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────
    public static class DashboardServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            HttpSession session = req.getSession(false);
            if (session == null || !Boolean.TRUE.equals(session.getAttribute("authenticated"))) {
                resp.sendRedirect("/login");
                return;
            }
            String username = (String) session.getAttribute("username");
            resp.setContentType("text/html;charset=UTF-8");
            resp.getWriter().println("""
                    <!DOCTYPE html>
                    <html>
                    <head><title>Furnace - Dashboard</title>
                    <style>
                      body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;margin:40px auto;max-width:700px;}
                      h1{color:#e94560;} h2{color:#0f9b8e;}
                      textarea{width:100%;height:100px;background:#16213e;color:#e0e0e0;border:1px solid #0f3460;padding:8px;box-sizing:border-box;}
                      button{background:#e94560;color:#fff;border:none;padding:10px 20px;cursor:pointer;}
                      pre{background:#16213e;padding:14px;overflow-x:auto;}
                    </style>
                    </head>
                    <body>
                    <h1>Furnace Dashboard</h1>
                    <p>Welcome, <strong>""" + username + """
                    </strong></p>
                    <h2>Report Generator</h2>
                    <p>Paste a Freemarker template below and render it:</p>
                    <form id="rf">
                      <textarea id="tpl" placeholder="Enter Freemarker template...">Hello ${name}!</textarea><br/><br/>
                      <button type="button" onclick="render()">Render Report</button>
                    </form>
                    <pre id="out"></pre>
                    <script>
                    async function render(){
                      const tpl = document.getElementById('tpl').value;
                      const r = await fetch('/report',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({template:tpl})});
                      const t = await r.text();
                      document.getElementById('out').textContent = t;
                    }
                    </script>
                    </body></html>
                    """);
        }
    }

    // ── Report ────────────────────────────────────────────────────────────────
    public static class ReportServlet extends HttpServlet {
        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            HttpSession session = req.getSession(false);
            if (session == null || !Boolean.TRUE.equals(session.getAttribute("authenticated"))) {
                resp.setStatus(403);
                resp.getWriter().println("Forbidden");
                return;
            }

            String templateStr;
            String ct = req.getContentType();
            if (ct != null && ct.contains("application/json")) {
                ObjectMapper om = new ObjectMapper();
                JsonNode body = om.readTree(req.getInputStream());
                templateStr = body.path("template").asText();
            } else {
                templateStr = req.getParameter("template");
            }

            if (templateStr == null || templateStr.isEmpty()) {
                resp.setStatus(400);
                resp.getWriter().println("Missing 'template' field");
                return;
            }

            resp.setContentType("text/plain;charset=UTF-8");
            PrintWriter out = resp.getWriter();

            try {
                Template tpl = new Template("user_tpl", templateStr, FM_CFG);
                Map<String, Object> model = new HashMap<>();
                model.put("name", "World");
                StringWriter sw = new StringWriter();
                tpl.process(model, sw);
                out.print(sw.toString());
            } catch (Exception e) {
                resp.setStatus(500);
                out.println("Template error: " + e.getMessage());
            }
        }
    }
}
