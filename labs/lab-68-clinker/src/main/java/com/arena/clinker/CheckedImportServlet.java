package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.ObjectInputStream;
import java.io.ObjectInputFilter;
import java.io.InvalidClassException;
import java.io.PrintWriter;
import java.util.Set;

public class CheckedImportServlet extends HttpServlet {

    private static final Set<String> ALLOWED_CLASSES = Set.of(
        "com.arena.clinker.ImportData",
        "java.util.HashMap",
        "java.util.ArrayList",
        "java.lang.String",
        "java.lang.Integer",
        "java.lang.Long"
    );

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        String contentType = req.getContentType();
        if (contentType == null || !contentType.contains("application/x-java-serialized-object")) {
            resp.setStatus(400);
            out.println("{\"error\":\"Content-Type must be application/x-java-serialized-object\"}");
            return;
        }

        try {
            ObjectInputStream ois = new ObjectInputStream(req.getInputStream());
            ois.setObjectInputFilter(info -> {
                Class<?> cl = info.serialClass();
                if (cl != null && !ALLOWED_CLASSES.contains(cl.getName())) {
                    return ObjectInputFilter.Status.REJECTED;
                }
                return ObjectInputFilter.Status.UNDECIDED;
            });
            Object obj = ois.readObject();
            ois.close();
            resp.setStatus(200);
            out.println("{\"status\":\"imported\",\"type\":\"" + obj.getClass().getSimpleName() + "\"}");
        } catch (java.io.InvalidClassException e) {
            resp.setStatus(400);
            out.println("{\"error\":\"Deserialization blocked: class not in allowlist\"}");
        } catch (Exception e) {
            resp.setStatus(500);
            out.println("{\"error\":\"Import failed: " + e.getClass().getSimpleName() + "\"}");
        }
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("text/html");
        resp.getWriter().println("<!DOCTYPE html><html><head><title>Clinker - Data Import</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}"
            + "h1{color:#e94560}code{background:#0f3460;padding:2px 6px}.note{color:#4ecca3}"
            + "a{color:#e94560}</style></head><body>"
            + "<h1>Data Import</h1>"
            + "<p class='note'>&#10003; Type filter active — only registered types permitted</p>"
            + "<p><a href='/'>Back to Dashboard</a></p>"
            + "</body></html>");
    }
}
