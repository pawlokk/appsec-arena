package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;
import java.util.HashMap;
import java.util.Map;

public class V2LoginServlet extends HttpServlet {

    private static final Map<String, String[]> USERS = new HashMap<>();

    static {
        USERS.put("admin", new String[]{"Cl1nk3r@dm1n!", "admin", "1"});
        USERS.put("user",  new String[]{"password123",   "user",  "2"});
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        String n = req.getParameter("username");
        String p = req.getParameter("password");

        if (n == null || p == null) {
            resp.setStatus(400);
            resp.setContentType("application/json");
            resp.getWriter().println("{\"error\":\"username and password required\"}");
            return;
        }

        String[] u = USERS.get(n);
        if (u != null && u[0].equals(p)) {
            int i = Integer.parseInt(u[2]);
            String r = u[1];
            String t = TokenHandlerV2.create(i, r);
            resp.setContentType("application/json");
            resp.setStatus(200);
            resp.getWriter().println("{\"token\":\"" + t + "\"}");
            return;
        }

        resp.setStatus(401);
        resp.setContentType("application/json");
        resp.getWriter().println("{\"error\":\"invalid credentials\"}");
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("text/html");
        PrintWriter o = resp.getWriter();
        o.println("<!DOCTYPE html><html><head><title>Clinker v2 Login</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}"
            + "h1{color:#e94560}.note{color:#4ecca3}a{color:#e94560}</style></head><body>"
            + "<h1>v2 Login API</h1>"
            + "<p>POST with username= and password= form params.</p>"
            + "<p><a href='/login'>/login</a></p>"
            + "</body></html>");
    }
}
