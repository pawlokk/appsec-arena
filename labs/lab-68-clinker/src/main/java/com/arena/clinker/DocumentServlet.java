package com.arena.clinker;

import javax.servlet.http.Cookie;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;
import java.sql.*;

public class DocumentServlet extends HttpServlet {

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        TokenHandlerV1.SessionInfo session = getSession(req);
        if (session == null) {
            resp.setStatus(401);
            out.println("{\"error\":\"authentication required\"}");
            return;
        }

        String contentType = req.getContentType();
        if (contentType == null || !contentType.contains("application/x-clinker-job")) {
            resp.setStatus(400);
            out.println("{\"error\":\"Content-Type must be application/x-clinker-job\"}");
            return;
        }

        String payload = req.getParameter("payload");
        if (payload == null || payload.isBlank()) {
            payload = new String(req.getInputStream().readAllBytes()).trim();
        }
        if (payload.isEmpty()) {
            resp.setStatus(400);
            out.println("{\"error\":\"payload required\"}");
            return;
        }

        try (Connection conn = Database.getConnection()) {
            String sql = "INSERT INTO document_jobs (submitted_by, payload, status) VALUES (?, ?, 'pending') RETURNING id";
            PreparedStatement ps = conn.prepareStatement(sql);
            ps.setInt(1, session.userId);
            ps.setString(2, payload);
            ResultSet rs = ps.executeQuery();
            rs.next();
            int jobId = rs.getInt(1);
            out.println("{\"status\":\"queued\",\"job_id\":" + jobId + "}");
        } catch (Exception e) {
            resp.setStatus(500);
            out.println("{\"error\":\"failed to enqueue job\"}");
        }
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        TokenHandlerV1.SessionInfo session = getSession(req);
        if (session == null) {
            resp.setStatus(401);
            out.println("{\"error\":\"authentication required\"}");
            return;
        }

        String pathInfo = req.getPathInfo();
        if (pathInfo == null || pathInfo.equals("/")) {
            try (Connection conn = Database.getConnection()) {
                String sql = "SELECT id, status, result, created_at FROM document_jobs WHERE submitted_by = ? ORDER BY created_at DESC LIMIT 20";
                PreparedStatement ps = conn.prepareStatement(sql);
                ps.setInt(1, session.userId);
                ResultSet rs = ps.executeQuery();
                StringBuilder sb = new StringBuilder("[");
                boolean first = true;
                while (rs.next()) {
                    if (!first) sb.append(",");
                    sb.append("{\"id\":").append(rs.getInt(1))
                      .append(",\"status\":\"").append(rs.getString(2)).append("\"")
                      .append(",\"result\":").append(rs.getString(3) != null ? "\"" + rs.getString(3) + "\"" : "null")
                      .append("}");
                    first = false;
                }
                sb.append("]");
                out.println(sb);
            } catch (Exception e) {
                resp.setStatus(500);
                out.println("{\"error\":\"failed to list jobs\"}");
            }
        } else {
            String idStr = pathInfo.replaceAll("[^0-9]", "");
            try (Connection conn = Database.getConnection()) {
                String sql = "SELECT id, submitted_by, status, result, created_at FROM document_jobs WHERE id = ?";
                PreparedStatement ps = conn.prepareStatement(sql);
                ps.setInt(1, Integer.parseInt(idStr));
                ResultSet rs = ps.executeQuery();
                if (!rs.next()) {
                    resp.setStatus(404);
                    out.println("{\"error\":\"job not found\"}");
                    return;
                }
                out.println("{\"id\":" + rs.getInt(1)
                    + ",\"status\":\"" + rs.getString(3) + "\""
                    + ",\"result\":" + (rs.getString(4) != null ? "\"" + rs.getString(4) + "\"" : "null")
                    + "}");
            } catch (Exception e) {
                resp.setStatus(500);
                out.println("{\"error\":\"failed to fetch job\"}");
            }
        }
    }

    private TokenHandlerV1.SessionInfo getSession(HttpServletRequest req) {
        Cookie[] cookies = req.getCookies();
        if (cookies == null) return null;
        for (Cookie c : cookies) {
            if ("session".equals(c.getName())) {
                return TokenHandlerV1.parseToken(c.getValue());
            }
        }
        return null;
    }
}
