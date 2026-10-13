package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;
import java.sql.*;

public class AlertServlet extends HttpServlet {

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        TokenHandlerV1.SessionInfo s = HomeServlet.getSession(req);
        if (s == null) { resp.setStatus(401); return; }
        if (!s.isAdmin()) { resp.setStatus(403); return; }

        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        try (Connection conn = Database.getConnection()) {
            ResultSet rs = conn.createStatement().executeQuery(
                "SELECT id, alert_type, target_user, detail, resolved, created_at FROM security_alerts ORDER BY id DESC"
            );
            StringBuilder b = new StringBuilder("[");
            boolean first = true;
            while (rs.next()) {
                if (!first) b.append(",");
                first = false;
                b.append("{")
                 .append("\"id\":").append(rs.getInt("id")).append(",")
                 .append("\"alert_type\":\"").append(esc(rs.getString("alert_type"))).append("\",")
                 .append("\"target_user\":\"").append(esc(rs.getString("target_user"))).append("\",")
                 .append("\"detail\":\"").append(esc(rs.getString("detail"))).append("\",")
                 .append("\"resolved\":").append(rs.getBoolean("resolved")).append(",")
                 .append("\"created_at\":\"").append(esc(String.valueOf(rs.getTimestamp("created_at")))).append("\"")
                 .append("}");
            }
            b.append("]");
            out.println(b);
        } catch (Exception e) {
            resp.setStatus(500);
            out.println("{\"error\":\"" + esc(e.getMessage()) + "\"}");
        }
    }

    @Override
    protected void doDelete(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        TokenHandlerV1.SessionInfo s = HomeServlet.getSession(req);
        if (s == null) { resp.setStatus(401); return; }
        if (!s.isAdmin()) { resp.setStatus(403); return; }

        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        String path = req.getPathInfo();
        try (Connection conn = Database.getConnection()) {
            if (path == null || path.equals("/")) {
                conn.createStatement().execute("DELETE FROM security_alerts");
                out.println("{\"status\":\"all alerts cleared\"}");
            } else {
                int id = Integer.parseInt(path.substring(1));
                PreparedStatement ps = conn.prepareStatement("DELETE FROM security_alerts WHERE id = ?");
                ps.setInt(1, id);
                int rows = ps.executeUpdate();
                if (rows == 0) { resp.setStatus(404); out.println("{\"error\":\"not found\"}"); }
                else out.println("{\"status\":\"deleted\",\"id\":" + id + "}");
            }
        } catch (NumberFormatException e) {
            resp.setStatus(400);
            out.println("{\"error\":\"invalid id\"}");
        } catch (Exception e) {
            resp.setStatus(500);
            out.println("{\"error\":\"" + esc(e.getMessage()) + "\"}");
        }
    }

    private String esc(String s) {
        if (s == null) return "";
        return s.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n").replace("\r", "");
    }
}
