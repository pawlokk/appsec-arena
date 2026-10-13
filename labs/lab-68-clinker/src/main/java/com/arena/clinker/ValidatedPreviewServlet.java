package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.InputStream;
import java.io.PrintWriter;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Set;

public class ValidatedPreviewServlet extends HttpServlet {

    private static final Set<String> ALLOWED = Set.of(
        "example.com",
        "docs.clinker.io",
        "cdn.assets.io"
    );

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        TokenHandlerV1.SessionInfo s = HomeServlet.getSession(req);
        if (s == null) {
            resp.sendRedirect("/login");
            return;
        }

        String u = req.getParameter("url");
        if (u == null || u.isEmpty()) {
            sf(resp, "", "");
            return;
        }

        try {
            URI p = new URI(u);
            String h = p.getHost();
            if (h == null || !ALLOWED.contains(h)) {
                resp.setStatus(400);
                resp.setContentType("text/html");
                resp.getWriter().println("<h2>Host not in allowlist. Permitted: " + ALLOWED + "</h2>");
                return;
            }
        } catch (Exception e) {
            resp.setStatus(400);
            resp.getWriter().println("Invalid URL");
            return;
        }

        try {
            URL r = new URL(u);
            HttpURLConnection c = (HttpURLConnection) r.openConnection();
            c.setRequestMethod("GET");
            c.setConnectTimeout(5000);
            c.setReadTimeout(5000);
            c.setInstanceFollowRedirects(false);

            int st = c.getResponseCode();
            InputStream is = st >= 400 ? c.getErrorStream() : c.getInputStream();
            String b = is == null ? "" : new String(is.readAllBytes(), StandardCharsets.UTF_8);
            if (b.length() > 4096) b = b.substring(0, 4096) + "\n... [truncated]";

            sf(resp, u, eh(b));
        } catch (Exception e) {
            sf(resp, u, "Error: " + eh(e.getMessage()));
        }
    }

    private void sf(HttpServletResponse resp, String u, String r) throws IOException {
        resp.setContentType("text/html");
        PrintWriter o = resp.getWriter();
        o.println("<!DOCTYPE html><html><head><title>Clinker - Link Preview</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;margin:0;padding:0}"
            + "nav{background:#16213e;padding:1rem 2rem;border-bottom:1px solid #0f3460}"
            + ".container{padding:2rem}h1{color:#e94560}.note{color:#4ecca3;font-size:.85rem}"
            + "input[type=text]{width:70%;padding:8px;background:#0f3460;border:1px solid #444;color:#eee}"
            + "button{background:#e94560;color:#fff;border:none;padding:9px 18px;cursor:pointer}"
            + "pre{background:#0f3460;padding:1rem;overflow-x:auto}"
            + "a{color:#e94560}</style></head><body>"
            + "<nav><span>&#9670; Clinker — <a href='/'>Dashboard</a></span></nav>"
            + "<div class='container'><h1>Link Preview</h1>"
            + "<p class='note'>&#10003; Allowlist-based protection (example.com, docs.clinker.io, cdn.assets.io)</p>"
            + "<form method='get' action='/validated-preview'>"
            + "<input type='text' name='url' value='" + eh(u) + "' placeholder='https://example.com'> "
            + "<button type='submit'>Fetch</button>"
            + "</form>"
            + (r.isEmpty() ? "" : "<h3>Response:</h3><pre>" + r + "</pre>")
            + "</div></body></html>");
    }

    private String eh(String s) {
        if (s == null) return "";
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("\"", "&quot;");
    }
}
