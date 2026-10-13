package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;
import java.nio.file.Files;
import java.nio.file.Paths;

public class AdminServlet extends HttpServlet {

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        TokenHandlerV1.SessionInfo s = HomeServlet.getSession(req);

        if (s == null) {
            resp.sendRedirect("/login");
            return;
        }

        if (!s.isAdmin()) {
            resp.setStatus(403);
            resp.setContentType("text/html");
            resp.getWriter().println("<h1>403 Forbidden</h1><p>Admin access required.</p>");
            return;
        }

        String f = "ARENA{LOCAL_placeholder}";
        try {
            f = new String(Files.readAllBytes(Paths.get("/flag_local.txt"))).trim();
        } catch (Exception e) {
        }

        resp.setContentType("text/html");
        PrintWriter o = resp.getWriter();
        o.println("<!DOCTYPE html><html><head><title>Clinker - Admin</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;margin:0;padding:0}"
            + "nav{background:#16213e;padding:1rem 2rem;display:flex;justify-content:space-between;border-bottom:1px solid #0f3460}"
            + ".container{padding:2rem}h1{color:#e94560}"
            + ".flag-box{background:#0f3460;border:2px solid #e94560;padding:1.5rem;border-radius:4px;font-size:1.2rem;font-weight:bold;color:#ffd700;word-break:break-all}"
            + ".card{background:#16213e;padding:1.5rem;border:1px solid #0f3460;border-radius:4px;margin-bottom:1rem}"
            + "a{color:#e94560}</style></head><body>"
            + "<nav><span>&#9670; Clinker Enterprise — Admin</span>"
            + "<span><a href='/'>Dashboard</a> &nbsp;<a href='/logout'>Logout</a></span></nav>"
            + "<div class='container'>"
            + "<h1>Admin Panel</h1>"
            + "<div class='card'><h3>System Flag</h3>"
            + "<div class='flag-box'>" + f + "</div></div>"
            + "<div class='card'><h3>Admin Session Info</h3>"
            + "<p>userId=" + s.userId + " role=" + s.role + "</p>"
            + "<p>Session expires at: " + (System.currentTimeMillis() / 1000 + 86400) + "</p></div>"
            + "</div></body></html>");
    }
}
