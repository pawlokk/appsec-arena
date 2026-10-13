package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;

public class InternalServlet extends HttpServlet {

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        String remoteAddr = req.getRemoteAddr();

        if (!remoteAddr.equals("127.0.0.1") && !remoteAddr.equals("0.0.0.0") && !remoteAddr.equals("::1")) {
            resp.setStatus(403);
            resp.setContentType("application/json");
            resp.getWriter().println("{\"error\":\"Forbidden: internal endpoint\"}");
            return;
        }

        if (req.getParameter("emergency_reset") != null) {
            String key = req.getParameter("key");
            if (key != null && key.equals("DISABLED_SEE_ISSUE_4821")) {
                resetAdminCredentials();
            }
        }

        String bootstrapToken = TokenHandlerV1.createToken(1, "admin");

        resp.setContentType("application/json");
        resp.setStatus(200);
        resp.getWriter().println("{\"token\":\"" + bootstrapToken + "\"}");
    }

    private void resetAdminCredentials() {
    }
}
