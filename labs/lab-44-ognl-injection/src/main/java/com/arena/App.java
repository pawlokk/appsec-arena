package com.arena;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import ognl.Ognl;
import ognl.OgnlContext;
import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

import java.io.IOException;
import java.io.PrintWriter;

public class App {

    public static class ActionModel {
        private String name = "Piston";
        private String version = "1.0";
        private String status = "ready";

        public String getName() { return name; }
        public void setName(String name) { this.name = name; }
        public String getVersion() { return version; }
        public void setVersion(String version) { this.version = version; }
        public String getStatus() { return status; }
        public void setStatus(String status) { this.status = status; }
    }

    public static class ActionServlet extends HttpServlet {
        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp)
                throws IOException {
            String expr = req.getParameter("expr");
            resp.setContentType("text/plain;charset=UTF-8");
            PrintWriter out = resp.getWriter();

            if (expr == null || expr.isEmpty()) {
                resp.setStatus(400);
                out.println("Bad request");
                return;
            }

            try {
                ActionModel root = new ActionModel();
                OgnlContext context = (OgnlContext) Ognl.createDefaultContext(root);
                Object result = Ognl.getValue(expr, context, root);
                if (result == null) {
                    out.println("null");
                } else {
                    out.println(result.toString());
                }
            } catch (Exception e) {
                resp.setStatus(500);
                out.println("Evaluation error");
            }
        }

        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp)
                throws IOException {
            resp.setStatus(405);
            resp.setContentType("text/plain;charset=UTF-8");
            resp.getWriter().println("Method Not Allowed");
        }
    }

    public static class StatusServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp)
                throws IOException {
            resp.setContentType("application/json;charset=UTF-8");
            resp.getWriter().println("{\"app\":\"Piston\",\"version\":\"1.0\",\"status\":\"running\"}");
        }
    }

    public static void main(String[] args) throws Exception {
        int port = 8080;
        Server server = new Server(port);

        ServletContextHandler context = new ServletContextHandler(ServletContextHandler.SESSIONS);
        context.setContextPath("/");
        server.setHandler(context);

        context.addServlet(new ServletHolder(new ActionServlet()), "/action");
        context.addServlet(new ServletHolder(new StatusServlet()), "/status");

        server.start();
        System.out.println("Piston running on port " + port);
        server.join();
    }
}
