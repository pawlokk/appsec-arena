package com.arena.clinker;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.ByteArrayInputStream;
import java.io.IOException;
import java.io.ObjectInputFilter;
import java.io.ObjectInputStream;
import java.io.PrintWriter;

public class ImportServlet extends HttpServlet {

    private static final int MAX_SIZE = 1024 * 1024;

    private static final String[] FILTER_RULES = {
        "org.apache.commons.collections",
        "org.apache.commons.beanutils",
        "org.springframework.beans",
        "org.springframework.core",
        "org.springframework.aop",
        "com.sun.org.apache.xalan",
        "com.sun.org.apache.xml.internal.security",
        "org.jboss",
        "org.codehaus.groovy",
        "groovy.lang",
        "sun.rmi",
        "java.rmi",
        "com.sun.jndi",
        "javax.script",
        "net.sf.cglib",
        "javassist",
        "org.mozilla.javascript",
        "com.mchange",
        "org.hibernate",
        "com.rometools",
        "org.apache.myfaces",
        "com.vaadin",
        "org.aspectj",
        "clojure",
        "ysoserial",
        "com.caucho",
    };

    private static final ObjectInputFilter CHAIN_FILTER = filterInfo -> {
        Class<?> cls = filterInfo.serialClass();
        if (cls == null) return ObjectInputFilter.Status.UNDECIDED;
        String name = cls.getName();
        for (String prefix : FILTER_RULES) {
            if (name.startsWith(prefix)) return ObjectInputFilter.Status.REJECTED;
        }
        return ObjectInputFilter.Status.UNDECIDED;
    };

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        if (req.getContentLength() > MAX_SIZE) {
            resp.setStatus(413);
            out.println("{\"error\":\"Payload too large (max 1MB)\"}");
            return;
        }

        String contentType = req.getContentType();
        if (contentType == null || !contentType.contains("application/x-java-serialized-object")) {
            resp.setStatus(400);
            out.println("{\"error\":\"Content-Type must be application/x-java-serialized-object\"}");
            return;
        }

        try {
            byte[] data = req.getInputStream().readAllBytes();
            if (data.length == 0) {
                resp.setStatus(400);
                out.println("{\"error\":\"Empty body\"}");
                return;
            }

            ByteArrayInputStream bais = new ByteArrayInputStream(data);
            ObjectInputStream ois = new ObjectInputStream(bais);
            ois.setObjectInputFilter(CHAIN_FILTER);
            Object obj = ois.readObject();
            ois.close();

            resp.setStatus(200);
            out.println("{\"status\":\"imported\",\"type\":\"" + obj.getClass().getSimpleName() + "\"}");
        } catch (Exception e) {
            resp.setStatus(500);
            out.println("{\"status\":\"error\",\"message\":\"Import failed: " + e.getClass().getSimpleName() + "\"}");
        }
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("text/html");
        resp.getWriter().println("<!DOCTYPE html><html><head><title>Clinker - Import</title>"
            + "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:2rem}"
            + "h1{color:#e94560}code{background:#0f3460;padding:2px 6px}"
            + "a{color:#e94560}</style></head><body>"
            + "<h1>Data Import</h1>"
            + "<p>This endpoint accepts binary job data for batch processing.</p>"
            + "<p>Usage: <code>POST /api/import</code></p>"
            + "<p>Content-Type: <code>application/x-java-serialized-object</code></p>"
            + "<p>Body: raw serialized Java object bytes</p>"
            + "<p>Size limit: 1MB</p>"
            + "<p><a href='/'>Back to Dashboard</a></p>"
            + "</body></html>");
    }
}
