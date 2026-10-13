package com.arena.clinker;

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;
import java.util.HashMap;
import java.util.Map;

public class AuthServlet extends HttpServlet {

    private static final Map<Integer, String[]> USERS = new HashMap<>();

    static {
        USERS.put(1, new String[]{"admin", "Cl1nk3r@dm1n!", "admin"});
        USERS.put(2, new String[]{"user", "password123", "user"});
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        String d = req.getServletPath();
        if ("/logout".equals(d)) {
            Cookie c = new Cookie("session", "");
            c.setMaxAge(0);
            c.setPath("/");
            resp.addCookie(c);
            resp.sendRedirect("/login");
            return;
        }
        resp.setContentType("text/html");
        PrintWriter o = resp.getWriter();
        o.println(lp(""));
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        String n = req.getParameter("username");
        String p = req.getParameter("password");

        for (Map.Entry<Integer, String[]> e : USERS.entrySet()) {
            String[] u = e.getValue();
            if (u[0].equals(n) && u[1].equals(p)) {
                int i = e.getKey();
                String x = u[2];
                String t = TokenHandlerV1.createToken(i, x);
                Cookie c = new Cookie("session", t);
                c.setPath("/");
                resp.addCookie(c);
                resp.sendRedirect("/");
                return;
            }
        }
        resp.setContentType("text/html");
        resp.getWriter().println(lp("Invalid credentials"));
    }

    private String lp(String e) {
        return "<!DOCTYPE html><html><head><title>Clinker - Login</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}"
            + ".box{background:#16213e;padding:2rem;border:1px solid #0f3460;border-radius:4px;min-width:320px}"
            + "h2{color:#e94560;margin-top:0}input{width:100%;padding:8px;margin:4px 0 12px;background:#0f3460;border:1px solid #444;color:#eee;box-sizing:border-box}"
            + "button{background:#e94560;color:#fff;border:none;padding:10px 20px;cursor:pointer;width:100%}"
            + ".err{color:#e94560;font-size:.85rem}</style></head><body>"
            + "<div class='box'><h2>Clinker Enterprise Portal</h2>"
            + (e.isEmpty() ? "" : "<p class='err'>" + e + "</p>")
            + "<form method='post' action='/login'>"
            + "<label>Username</label><input name='username' type='text'>"
            + "<label>Password</label><input name='password' type='password'>"
            + "<button type='submit'>Login</button>"
            + "</form></div></body></html>";
    }
}
