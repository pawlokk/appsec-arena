package io.clockwork;

import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.DefaultServlet;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import java.io.*;
import java.sql.*;
import java.util.Random;

public class App {

    static Connection db;

    public static void main(String[] args) throws Exception {
        db = DriverManager.getConnection("jdbc:hsqldb:mem:clockwork", "SA", "");
        initDb();

        Server server = new Server(5000);
        ServletContextHandler ctx = new ServletContextHandler(ServletContextHandler.SESSIONS);
        ctx.setContextPath("/");

        File staticDir = new File("/app/static");
        if (!staticDir.exists()) staticDir.mkdirs();
        ctx.setResourceBase(staticDir.getAbsolutePath());
        ServletHolder defaultHolder = new ServletHolder("default", DefaultServlet.class);
        defaultHolder.setInitParameter("dirAllowed", "false");
        ctx.addServlet(defaultHolder, "/static/*");

        ctx.addServlet(new ServletHolder(new HomeServlet()), "/");
        ctx.addServlet(new ServletHolder(new LoginServlet()), "/login");
        ctx.addServlet(new ServletHolder(new LogoutServlet()), "/logout");
        ctx.addServlet(new ServletHolder(new ResetRequestServlet()), "/reset/request");
        ctx.addServlet(new ServletHolder(new ResetVerifyServlet()), "/reset/verify");
        ctx.addServlet(new ServletHolder(new ResetFormServlet()), "/reset/form");
        ctx.addServlet(new ServletHolder(new DashboardServlet()), "/dashboard");
        ctx.addServlet(new ServletHolder(new AdminDbServlet()), "/admin/db");

        server.setHandler(ctx);
        server.start();
        System.out.println("[*] Clockwork started on port 5000");
        server.join();
    }

    static void initDb() throws Exception {
        try (Statement st = db.createStatement()) {
            st.execute("CREATE TABLE users (id INT IDENTITY PRIMARY KEY, email VARCHAR(255) UNIQUE, password VARCHAR(255), role VARCHAR(50))");
            st.execute("CREATE TABLE reset_tokens (id INT IDENTITY PRIMARY KEY, email VARCHAR(255), token VARCHAR(10), used BOOLEAN DEFAULT FALSE)");

            String flagLocal = System.getenv("FLAG_LOCAL");
            if (flagLocal == null) flagLocal = "ARENA{LOCAL_placeholder}";

            String adminPass = System.getenv("ADMIN_PASS");
            if (adminPass == null || adminPass.isEmpty()) adminPass = "changeme-" + Long.toHexString(System.nanoTime());

            st.execute("INSERT INTO users (email, password, role) VALUES ('admin@clockwork.io', '" + adminPass + "', 'admin')");
            st.execute("INSERT INTO users (email, password, role) VALUES ('user@clockwork.io', 'password123', 'user')");

            st.execute("CREATE TABLE settings (k VARCHAR(255) PRIMARY KEY, v VARCHAR(1024))");
            st.execute("INSERT INTO settings (k, v) VALUES ('flag_local', '" + flagLocal + "')");
        }
        System.out.println("[*] Database initialized");
    }

    static String escHtml(String s) {
        if (s == null) return "";
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("\"", "&quot;");
    }

    // ---------- HOME ----------
    static class HomeServlet extends HttpServlet {
        @Override protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
            res.sendRedirect("/login");
        }
    }

    // ---------- LOGIN ----------
    static class LoginServlet extends HttpServlet {
        @Override protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
            String msg = req.getParameter("msg") != null ? escHtml(req.getParameter("msg")) : "";
            res.setContentType("text/html;charset=utf-8");
            PrintWriter w = res.getWriter();
            w.println("<!DOCTYPE html><html><head><title>Clockwork Login</title>");
            w.println("<style>body{font-family:monospace;background:#111;color:#0f0;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}");
            w.println(".box{background:#1a1a1a;border:1px solid #0f0;padding:2rem;min-width:320px}");
            w.println("input{background:#0a0a0a;border:1px solid #0f0;color:#0f0;padding:.4rem;width:100%;box-sizing:border-box;margin:.3rem 0}");
            w.println("button{background:#0f0;color:#000;border:none;padding:.5rem 1rem;cursor:pointer;width:100%;margin-top:.5rem}");
            w.println("a{color:#0f0}h2{color:#0f0;margin-top:0}</style></head><body>");
            w.println("<div class='box'><h2>[ CLOCKWORK ]</h2>");
            if (!msg.isEmpty()) w.println("<p style='color:red'>" + msg + "</p>");
            w.println("<form method='POST'>");
            w.println("<label>Email</label><input name='email' type='email' required>");
            w.println("<label>Password</label><input name='password' type='password' required>");
            w.println("<button type='submit'>Login</button>");
            w.println("</form><br><a href='/reset/form'>Forgot password?</a></div></body></html>");
        }

        @Override protected void doPost(HttpServletRequest req, HttpServletResponse res) throws IOException, ServletException {
            String email = req.getParameter("email");
            String pass = req.getParameter("password");
            try (PreparedStatement ps = db.prepareStatement("SELECT id, role FROM users WHERE email=? AND password=?")) {
                ps.setString(1, email);
                ps.setString(2, pass);
                ResultSet rs = ps.executeQuery();
                if (rs.next()) {
                    HttpSession sess = req.getSession(true);
                    sess.setAttribute("email", email);
                    sess.setAttribute("role", rs.getString("role"));
                    res.sendRedirect("/dashboard");
                } else {
                    res.sendRedirect("/login?msg=Invalid+credentials");
                }
            } catch (SQLException e) {
                res.sendRedirect("/login?msg=DB+error");
            }
        }
    }

    // ---------- LOGOUT ----------
    static class LogoutServlet extends HttpServlet {
        @Override protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
            HttpSession sess = req.getSession(false);
            if (sess != null) sess.invalidate();
            res.sendRedirect("/login");
        }
    }

    // ---------- RESET FORM ----------
    static class ResetFormServlet extends HttpServlet {
        @Override protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
            String msg = req.getParameter("msg") != null ? escHtml(req.getParameter("msg")) : "";
            res.setContentType("text/html;charset=utf-8");
            PrintWriter w = res.getWriter();
            w.println("<!DOCTYPE html><html><head><title>Reset Password</title>");
            w.println("<style>body{font-family:monospace;background:#111;color:#0f0;display:flex;justify-content:center;align-items:center;height:100vh;margin:0}");
            w.println(".box{background:#1a1a1a;border:1px solid #0f0;padding:2rem;min-width:320px}");
            w.println("input{background:#0a0a0a;border:1px solid #0f0;color:#0f0;padding:.4rem;width:100%;box-sizing:border-box;margin:.3rem 0}");
            w.println("button{background:#0f0;color:#000;border:none;padding:.5rem 1rem;cursor:pointer;width:100%;margin-top:.5rem}");
            w.println("a{color:#0f0}h2{color:#0f0;margin-top:0}</style></head><body>");
            w.println("<div class='box'><h2>[ PASSWORD RESET ]</h2>");
            if (!msg.isEmpty()) w.println("<p style='color:#ff0'>" + msg + "</p>");
            w.println("<p>Step 1: Request a reset token</p>");
            w.println("<form method='POST' action='/reset/request'>");
            w.println("<label>Email</label><input name='email' type='email' required>");
            w.println("<button type='submit'>Send Reset Token</button></form>");
            w.println("<br><hr style='border-color:#0f0'><br>");
            w.println("<p>Step 2: Enter your token</p>");
            w.println("<form method='POST' action='/reset/verify'>");
            w.println("<label>Email</label><input name='email' type='email' required>");
            w.println("<label>Token (6 digits)</label><input name='token' type='text' maxlength='6' required>");
            w.println("<label>New Password</label><input name='newpass' type='password' required>");
            w.println("<button type='submit'>Reset Password</button></form>");
            w.println("<br><a href='/login'>Back to login</a></div></body></html>");
        }
    }

    // ---------- RESET REQUEST ----------
    static class ResetRequestServlet extends HttpServlet {
        @Override protected void doPost(HttpServletRequest req, HttpServletResponse res) throws IOException {
            String email = req.getParameter("email");
            if (email == null || email.isEmpty()) {
                res.sendRedirect("/reset/form?msg=Email+required");
                return;
            }

            try {
                PreparedStatement chk = db.prepareStatement("SELECT id FROM users WHERE email=?");
                chk.setString(1, email);
                ResultSet rs = chk.executeQuery();
                if (!rs.next()) {
                    res.sendRedirect("/reset/form?msg=If+that+email+exists,+a+token+has+been+sent");
                    return;
                }

                long seed = System.currentTimeMillis();
                int tokenInt = new Random(seed).nextInt(999999);
                String token = String.format("%06d", tokenInt);

                PreparedStatement del = db.prepareStatement("DELETE FROM reset_tokens WHERE email=?");
                del.setString(1, email);
                del.executeUpdate();

                PreparedStatement ins = db.prepareStatement("INSERT INTO reset_tokens (email, token, used) VALUES (?, ?, FALSE)");
                ins.setString(1, email);
                ins.setString(2, token);
                ins.executeUpdate();

                res.sendRedirect("/reset/form?msg=If+that+email+exists,+a+token+has+been+sent");
            } catch (SQLException e) {
                res.sendRedirect("/reset/form?msg=DB+error:+" + escHtml(e.getMessage()));
            }
        }
    }

    // ---------- RESET VERIFY ----------
    static class ResetVerifyServlet extends HttpServlet {
        @Override protected void doPost(HttpServletRequest req, HttpServletResponse res) throws IOException {
            String email = req.getParameter("email");
            String token = req.getParameter("token");
            String newpass = req.getParameter("newpass");

            if (email == null || token == null || newpass == null) {
                res.sendRedirect("/reset/form?msg=All+fields+required");
                return;
            }

            try {
                PreparedStatement ps = db.prepareStatement(
                    "SELECT id FROM reset_tokens WHERE email=? AND token=? AND used=FALSE");
                ps.setString(1, email);
                ps.setString(2, token);
                ResultSet rs = ps.executeQuery();
                if (rs.next()) {
                    PreparedStatement upd = db.prepareStatement(
                        "UPDATE reset_tokens SET used=TRUE WHERE email=? AND token=?");
                    upd.setString(1, email);
                    upd.setString(2, token);
                    upd.executeUpdate();

                    PreparedStatement updPass = db.prepareStatement(
                        "UPDATE users SET password=? WHERE email=?");
                    updPass.setString(1, newpass);
                    updPass.setString(2, email);
                    updPass.executeUpdate();

                    res.sendRedirect("/login?msg=Password+reset+successful");
                } else {
                    res.sendRedirect("/reset/form?msg=Invalid+or+expired+token");
                }
            } catch (SQLException e) {
                res.sendRedirect("/reset/form?msg=DB+error");
            }
        }
    }

    // ---------- DASHBOARD ----------
    static class DashboardServlet extends HttpServlet {
        @Override protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
            HttpSession sess = req.getSession(false);
            if (sess == null || sess.getAttribute("email") == null) {
                res.sendRedirect("/login");
                return;
            }
            String email = (String) sess.getAttribute("email");
            String role = (String) sess.getAttribute("role");

            String flagLocal = "";
            if ("admin".equals(role)) {
                try (Statement st = db.createStatement()) {
                    ResultSet rs = st.executeQuery("SELECT v FROM settings WHERE k='flag_local'");
                    if (rs.next()) flagLocal = rs.getString(1);
                } catch (SQLException ignored) {}
            }

            res.setContentType("text/html;charset=utf-8");
            PrintWriter w = res.getWriter();
            w.println("<!DOCTYPE html><html><head><title>Clockwork Dashboard</title>");
            w.println("<style>body{font-family:monospace;background:#111;color:#0f0;margin:2rem}");
            w.println(".flag{background:#001a00;border:2px solid #0f0;padding:1rem;margin:1rem 0;font-size:1.2rem}");
            w.println("a{color:#0f0}h1{color:#0f0}</style></head><body>");
            w.println("<h1>[ CLOCKWORK DASHBOARD ]</h1>");
            w.println("<p>Welcome, <strong>" + escHtml(email) + "</strong> | Role: <strong>" + escHtml(role) + "</strong></p>");
            if ("admin".equals(role)) {
                w.println("<div class='flag'><strong>FLAG:</strong> " + escHtml(flagLocal) + "</div>");
                w.println("<p><a href='/admin/db'>[ Admin DB Console ]</a></p>");
            }
            w.println("<p><a href='/logout'>[ Logout ]</a></p>");
            w.println("</body></html>");
        }
    }

    // ---------- ADMIN DB CONSOLE ----------
    static class AdminDbServlet extends HttpServlet {
        @Override protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
            HttpSession sess = req.getSession(false);
            if (sess == null || !"admin".equals(sess.getAttribute("role"))) {
                res.sendRedirect("/login?msg=Access+denied");
                return;
            }
            res.setContentType("text/html;charset=utf-8");
            PrintWriter w = res.getWriter();
            renderConsole(w, "", "", false);
        }

        @Override protected void doPost(HttpServletRequest req, HttpServletResponse res) throws IOException {
            HttpSession sess = req.getSession(false);
            if (sess == null || !"admin".equals(sess.getAttribute("role"))) {
                res.sendRedirect("/login?msg=Access+denied");
                return;
            }

            String sql = req.getParameter("sql");
            if (sql == null) sql = "";

            StringBuilder result = new StringBuilder();
            boolean isError = false;

            try (Statement st = db.createStatement()) {
                boolean hasResultSet = st.execute(sql);
                if (hasResultSet) {
                    ResultSet rs = st.getResultSet();
                    ResultSetMetaData meta = rs.getMetaData();
                    int cols = meta.getColumnCount();
                    result.append("<table border='1' style='border-collapse:collapse;border-color:#0f0'><tr>");
                    for (int i = 1; i <= cols; i++) {
                        result.append("<th style='padding:.3rem .6rem'>").append(escHtml(meta.getColumnName(i))).append("</th>");
                    }
                    result.append("</tr>");
                    while (rs.next()) {
                        result.append("<tr>");
                        for (int i = 1; i <= cols; i++) {
                            String val = rs.getString(i);
                            result.append("<td style='padding:.3rem .6rem'>").append(escHtml(val == null ? "NULL" : val)).append("</td>");
                        }
                        result.append("</tr>");
                    }
                    result.append("</table>");
                } else {
                    result.append("<p>OK — rows affected: ").append(st.getUpdateCount()).append("</p>");
                }
            } catch (SQLException e) {
                isError = true;
                result.append("<p style='color:red'>ERROR: ").append(escHtml(e.getMessage())).append("</p>");
            }

            res.setContentType("text/html;charset=utf-8");
            PrintWriter w = res.getWriter();
            renderConsole(w, sql, result.toString(), isError);
        }

        private void renderConsole(PrintWriter w, String prevSql, String result, boolean isError) {
            w.println("<!DOCTYPE html><html><head><title>DB Console</title>");
            w.println("<style>body{font-family:monospace;background:#111;color:#0f0;margin:2rem}");
            w.println("textarea{background:#0a0a0a;border:1px solid #0f0;color:#0f0;width:100%;height:120px;font-family:monospace;font-size:1rem;padding:.5rem;box-sizing:border-box}");
            w.println("button{background:#0f0;color:#000;border:none;padding:.5rem 1.5rem;cursor:pointer;margin-top:.5rem}");
            w.println(".result{background:#001a00;border:1px solid #0f0;padding:1rem;margin-top:1rem;overflow-x:auto}");
            w.println("a{color:#0f0}h1{color:#0f0}table{color:#0f0}</style></head><body>");
            w.println("<h1>[ HSQLDB ADMIN CONSOLE ]</h1>");
            w.println("<p><a href='/dashboard'>&larr; Dashboard</a></p>");
            w.println("<form method='POST'>");
            w.println("<textarea name='sql' placeholder='Enter HSQL statement...'>" + escHtml(prevSql) + "</textarea><br>");
            w.println("<button type='submit'>Execute</button></form>");
            if (!result.isEmpty()) {
                w.println("<div class='result'>" + result + "</div>");
            }
            w.println("</body></html>");
        }
    }
}
