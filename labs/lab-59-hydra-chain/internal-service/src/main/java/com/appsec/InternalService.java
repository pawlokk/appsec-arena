package com.appsec;

import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.server.Request;
import org.eclipse.jetty.server.handler.AbstractHandler;

import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.*;
import java.util.Base64;

public class InternalService extends AbstractHandler {

    private static final String API_KEY = System.getenv("INTERNAL_API_KEY");

    @Override
    public void handle(String target, Request baseRequest, HttpServletRequest request, HttpServletResponse response)
            throws IOException {
        baseRequest.setHandled(true);

        if ("GET".equals(request.getMethod()) && "/status".equals(target)) {
            response.setStatus(HttpServletResponse.SC_OK);
            response.setContentType("application/json");
            String apiKeyHeader = request.getHeader("X-API-Key");
            if (API_KEY.equals(apiKeyHeader)) {
                response.getWriter().write("{\"status\":\"ok\",\"auth\":\"valid\"}");
            } else {
                response.getWriter().write("{\"status\":\"ok\",\"auth\":\"none\"}");
            }
            return;
        }

        if ("POST".equals(request.getMethod()) && "/deserialize".equals(target)) {
            String apiKeyHeader = request.getHeader("X-API-Key");
            if (!API_KEY.equals(apiKeyHeader)) {
                response.setStatus(HttpServletResponse.SC_FORBIDDEN);
                response.getWriter().write("{\"error\":\"forbidden\"}");
                return;
            }

            try {
                ByteArrayOutputStream buf = new ByteArrayOutputStream();
                InputStream in = request.getInputStream();
                byte[] chunk = new byte[4096];
                int n;
                while ((n = in.read(chunk)) != -1) buf.write(chunk, 0, n);
                byte[] encoded = buf.toByteArray();
                byte[] decoded = Base64.getDecoder().decode(encoded);
                ObjectInputStream ois = new ObjectInputStream(new ByteArrayInputStream(decoded));
                Object obj = ois.readObject();
                response.setStatus(HttpServletResponse.SC_OK);
                response.setContentType("application/json");
                response.getWriter().write("{\"result\":\"" + obj.toString() + "\"}");
            } catch (Exception e) {
                response.setStatus(HttpServletResponse.SC_OK);
                response.setContentType("application/json");
                response.getWriter().write("{\"result\":\"processed\"}");
            }
            return;
        }

        response.setStatus(HttpServletResponse.SC_NOT_FOUND);
        response.getWriter().write("{\"error\":\"not found\"}");
    }

    public static void main(String[] args) throws Exception {
        int port = 9000;
        Server server = new Server(port);
        server.setHandler(new InternalService());
        server.start();
        System.out.println("Internal service running on port " + port);
        server.join();
    }
}
