package com.arena.clinker;

import com.arena.clinker.helpers.ClinkerTokenFactory;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;
import java.sql.*;
import java.util.HashMap;
import java.util.Map;

public class ResetServlet extends HttpServlet {

    private static final Map<String, String> pendingResets = new HashMap<>();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("text/html");
        PrintWriter out = resp.getWriter();
        String token = req.getParameter("token");
        if (token != null) {
            out.println(resetForm(token));
        } else {
            out.println(requestForm());
        }
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();
        String path = req.getPathInfo();

        if ("/request".equals(path)) {
            String username = req.getParameter("username");
            if (username == null || username.isBlank()) {
                resp.setStatus(400);
                out.println("{\"error\":\"username required\"}");
                return;
            }
            try (Connection conn = Database.getConnection()) {
                String sql = "SELECT id FROM users WHERE username = ?";
                PreparedStatement ps = conn.prepareStatement(sql);
                ps.setString(1, username);
                ResultSet rs = ps.executeQuery();
                if (!rs.next()) {
                    out.println("{\"status\":\"if that account exists, a reset token was issued\"}");
                    return;
                }
                String token = ClinkerTokenFactory.generateResetToken();
                pendingResets.put(username, token);
                out.println("{\"status\":\"token issued\",\"token\":\"" + token + "\"}");
            } catch (Exception e) {
                resp.setStatus(500);
                out.println("{\"error\":\"internal error\"}");
            }
        } else if ("/confirm".equals(path)) {
            String username = req.getParameter("username");
            String token = req.getParameter("token");
            String newPassword = req.getParameter("new_password");
            if (username == null || token == null || newPassword == null) {
                resp.setStatus(400);
                out.println("{\"error\":\"username, token, and new_password required\"}");
                return;
            }
            String stored = pendingResets.get(username);
            if (stored == null || !stored.equals(token)) {
                resp.setStatus(403);
                out.println("{\"error\":\"invalid or expired token\"}");
                return;
            }
            try (Connection conn = Database.getConnection()) {
                String sql = "UPDATE users SET password_hash = ? WHERE username = ?";
                PreparedStatement ps = conn.prepareStatement(sql);
                ps.setString(1, newPassword);
                ps.setString(2, username);
                ps.executeUpdate();
                String alertSql = "INSERT INTO security_alerts (alert_type, target_user, detail) VALUES (?, ?, ?)";
                PreparedStatement ap = conn.prepareStatement(alertSql);
                ap.setString(1, "password_reset");
                ap.setString(2, username);
                ap.setString(3, "Password reset completed via token from " + req.getRemoteAddr());
                ap.executeUpdate();
                pendingResets.remove(username);
                out.println("{\"status\":\"password updated\"}");
            } catch (Exception e) {
                resp.setStatus(500);
                out.println("{\"error\":\"internal error\"}");
            }
        } else {
            resp.setStatus(404);
            out.println("{\"error\":\"not found\"}");
        }
    }

    private String requestForm() {
        return "<!DOCTYPE html><html><head><title>Clinker - Reset Password</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}"
            + "input,button{padding:8px;margin:4px;background:#0f3460;color:#eee;border:1px solid #e94560}"
            + "h1{color:#e94560}a{color:#e94560}</style></head><body>"
            + "<h1>Password Reset</h1>"
            + "<form method='post' action='/reset/request'>"
            + "<input name='username' placeholder='Username' required /><br/>"
            + "<button type='submit'>Request Reset Token</button>"
            + "</form><br/><a href='/login'>Back to Login</a></body></html>";
    }

    private String resetForm(String token) {
        return "<!DOCTYPE html><html><head><title>Clinker - Set New Password</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}"
            + "input,button{padding:8px;margin:4px;background:#0f3460;color:#eee;border:1px solid #e94560}"
            + "h1{color:#e94560}</style></head><body>"
            + "<h1>Set New Password</h1>"
            + "<form method='post' action='/reset/confirm'>"
            + "<input name='username' placeholder='Username' required /><br/>"
            + "<input name='token' value='" + token + "' type='hidden' />"
            + "<input name='new_password' type='password' placeholder='New password' required /><br/>"
            + "<button type='submit'>Update Password</button>"
            + "</form></body></html>";
    }
}
