package com.arena.clinker;

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;

public class HomeServlet extends HttpServlet {

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        TokenHandlerV1.SessionInfo s = getSession(req);
        if (s == null) {
            resp.sendRedirect("/login");
            return;
        }

        resp.setContentType("text/html");
        PrintWriter o = resp.getWriter();
        o.println("<!DOCTYPE html><html><head><title>Clinker - Dashboard</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;margin:0;padding:0}"
            + "nav{background:#16213e;padding:1rem 2rem;display:flex;justify-content:space-between;border-bottom:1px solid #0f3460}"
            + ".container{padding:2rem}h1{color:#e94560}"
            + ".card{background:#16213e;padding:1.5rem;border:1px solid #0f3460;border-radius:4px;margin-bottom:1rem}"
            + "a{color:#e94560}code{background:#0f3460;padding:2px 6px;border-radius:2px}"
            + "</style></head><body>"
            + "<nav><span>&#9670; Clinker Enterprise</span>"
            + "<span>Logged in as userId=" + s.userId + " role=" + s.role
            + " &nbsp;<a href='/logout'>Logout</a></span></nav>"
            + "<div class='container'>"
            + "<h1>Dashboard</h1>"
            + "<div class='card'><h3>Link Preview</h3>"
            + "<p>Fetch a URL for link preview: <a href='/preview?url=https://example.com'>/preview?url=...</a></p></div>"
            + "<div class='card'><h3>Data Import</h3>"
            + "<p>Batch data import: <code>POST /api/import</code> (Content-Type: application/x-java-serialized-object)</p></div>"
            + (s.isAdmin() ? "<div class='card'><h3><a href='/admin'>Admin Panel</a></h3></div>" : "")
            + "</div></body></html>");
    }

    static TokenHandlerV1.SessionInfo getSession(HttpServletRequest req) {
        if (req.getCookies() == null) return null;
        for (Cookie c : req.getCookies()) {
            if ("session".equals(c.getName())) return TokenHandlerV1.parseToken(c.getValue());
        }
        return null;
    }
}
