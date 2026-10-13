package com.arena;

import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.*;
import java.nio.file.Files;
import java.util.Base64;

public class App {

    public static void main(String[] args) throws Exception {
        Server server = new Server(5000);

        ServletContextHandler context = new ServletContextHandler(ServletContextHandler.NO_SESSIONS);
        context.setContextPath("/");

        context.addServlet(new ServletHolder(new ImportServlet()), "/api/import");
        context.addServlet(new ServletHolder(new ExportResultServlet()), "/api/export-result");
        context.addServlet(new ServletHolder(new StatusServlet()), "/api/status");

        server.setHandler(context);
        server.start();
        server.join();
    }

    static class ImportServlet extends HttpServlet {
        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            resp.setContentType("application/json");
            try {
                BufferedReader reader = req.getReader();
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = reader.readLine()) != null) {
                    sb.append(line.trim());
                }
                String body = sb.toString();

                byte[] serializedBytes = Base64.getDecoder().decode(body);

                ByteArrayInputStream bais = new ByteArrayInputStream(serializedBytes);
                ObjectInputStream ois = new ObjectInputStream(bais);
                Object obj = ois.readObject();
                ois.close();

                resp.setStatus(200);
                resp.getWriter().write("{\"status\":\"imported\",\"type\":\"" + obj.getClass().getSimpleName() + "\"}");
            } catch (Exception e) {
                resp.setStatus(500);
                resp.getWriter().write("{\"status\":\"error\",\"message\":\"Import failed\"}");
            }
        }
    }

    static class ExportResultServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            resp.setContentType("application/json");
            File result = new File("/tmp/import_result");
            if (result.exists()) {
                String contents = new String(Files.readAllBytes(result.toPath())).trim();
                resp.setStatus(200);
                resp.getWriter().write("{\"ready\":true,\"data\":\"" + contents.replace("\"", "\\\"") + "\"}");
            } else {
                resp.setStatus(200);
                resp.getWriter().write("{\"ready\":false}");
            }
        }
    }

    static class StatusServlet extends HttpServlet {
        private static final String API_KEY_HEADER = "X-API-Key";

        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws IOException {
            resp.setContentType("application/json");
            String apiKey = req.getHeader(API_KEY_HEADER);
            String expectedKey = System.getenv("ADMIN_API_KEY");
            if (expectedKey == null || !expectedKey.equals(apiKey)) {
                resp.setStatus(401);
                resp.getWriter().write("{\"status\":\"unauthorized\"}");
                return;
            }
            String flagLocal = System.getenv("FLAG_LOCAL");
            if (flagLocal == null) flagLocal = "ARENA{LOCAL_placeholder}";
            resp.setStatus(200);
            resp.getWriter().write("{\"status\":\"ok\",\"flag\":\"" + flagLocal + "\"}");
        }
    }
}
