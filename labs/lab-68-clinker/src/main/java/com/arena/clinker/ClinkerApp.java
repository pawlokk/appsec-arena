package com.arena.clinker;

import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

public class ClinkerApp {

    public static void main(String[] args) throws Exception {
        Database.getConnection().close();

        long jobIntervalMs = 15_000;
        String intervalEnv = System.getenv("CLINKER_JOB_INTERVAL_MS");
        if (intervalEnv != null) {
            try { jobIntervalMs = Long.parseLong(intervalEnv); } catch (NumberFormatException ignored) {}
        }
        new JobProcessor(jobIntervalMs).start();

        Server server = new Server(5000);

        ServletContextHandler context = new ServletContextHandler(ServletContextHandler.NO_SESSIONS);
        context.setContextPath("/");

        context.addServlet(new ServletHolder(new AuthServlet()), "/login");
        context.addServlet(new ServletHolder(new AuthServlet()), "/logout");
        context.addServlet(new ServletHolder(new AdminServlet()), "/admin");
        context.addServlet(new ServletHolder(new PreviewServlet()), "/preview");
        context.addServlet(new ServletHolder(new ValidatedPreviewServlet()), "/validated-preview");
        context.addServlet(new ServletHolder(new ImportServlet()), "/api/import");
        context.addServlet(new ServletHolder(new CheckedImportServlet()), "/api/checked-import");
        context.addServlet(new ServletHolder(new DocumentServlet()), "/api/documents/*");
        context.addServlet(new ServletHolder(new ResetServlet()), "/reset/*");
        context.addServlet(new ServletHolder(new AlertServlet()), "/api/alerts/*");
        context.addServlet(new ServletHolder(new V2LoginServlet()), "/api/v2/login");
        context.addServlet(new ServletHolder(new InternalServlet()), "/internal/bootstrap/session");
        context.addServlet(new ServletHolder(new HomeServlet()), "/");

        server.setHandler(context);
        server.start();
        System.out.println("Clinker running on port 5000");
        server.join();
    }
}
