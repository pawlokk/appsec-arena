package com.arena;

import org.eclipse.jetty.server.Server;
import org.eclipse.jetty.servlet.ServletContextHandler;
import org.eclipse.jetty.servlet.ServletHolder;

import javax.servlet.ServletException;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.xml.transform.*;
import javax.xml.transform.stream.StreamResult;
import javax.xml.transform.stream.StreamSource;
import java.io.*;
import java.nio.charset.StandardCharsets;

public class App {

    public static void main(String[] args) throws Exception {
        Server server = new Server(5000);

        ServletContextHandler context = new ServletContextHandler(ServletContextHandler.SESSIONS);
        context.setContextPath("/");
        server.setHandler(context);

        context.addServlet(new ServletHolder(new IndexServlet()), "/");
        context.addServlet(new ServletHolder(new TransformServlet()), "/transform");

        server.start();
        System.out.println("Axis XSLT Processor running on port 5000");
        server.join();
    }

    public static class IndexServlet extends HttpServlet {
        @Override
        protected void doGet(HttpServletRequest req, HttpServletResponse resp)
                throws ServletException, IOException {
            resp.setContentType("text/html; charset=utf-8");
            PrintWriter out = resp.getWriter();
            out.println("<!DOCTYPE html>");
            out.println("<html><head><title>Axis - XML Report Processor</title>");
            out.println("<style>");
            out.println("body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; margin: 40px; }");
            out.println("h1 { color: #00d4ff; }");
            out.println("h2 { color: #a0a0c0; }");
            out.println("textarea { width: 100%; height: 200px; background: #0d0d1a; color: #00ff88; border: 1px solid #333; padding: 10px; font-family: monospace; }");
            out.println("input[type=submit] { background: #00d4ff; color: #000; border: none; padding: 10px 30px; cursor: pointer; font-size: 16px; margin-top: 10px; }");
            out.println(".hint { color: #666; font-size: 12px; margin-top: 5px; }");
            out.println("</style></head><body>");
            out.println("<h1>Axis XML Report Processor</h1>");
            out.println("<p>Submit your XML report and XSLT stylesheet for transformation.</p>");
            out.println("<form action='/transform' method='post'>");
            out.println("<h2>XML Input</h2>");
            out.println("<textarea name='xml'><?xml version=\"1.0\"?><report><title>Sales Report</title><data>Q1 revenue: $1,200,000</data></report></textarea>");
            out.println("<h2>XSLT Stylesheet</h2>");
            out.println("<textarea name='xslt'><?xml version=\"1.0\"?>");
            out.println("<xsl:stylesheet version=\"1.0\" xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\">");
            out.println("<xsl:output method=\"text\"/>");
            out.println("<xsl:template match=\"/\">");
            out.println("  Report: <xsl:value-of select=\"report/title\"/>");
            out.println("  Data: <xsl:value-of select=\"report/data\"/>");
            out.println("</xsl:template>");
            out.println("</xsl:stylesheet></textarea>");
            out.println("<br/><input type='submit' value='Transform'/>");
            out.println("</form>");
            out.println("</body></html>");
        }
    }

    public static class TransformServlet extends HttpServlet {
        @Override
        protected void doPost(HttpServletRequest req, HttpServletResponse resp)
                throws ServletException, IOException {
            resp.setContentType("text/html; charset=utf-8");
            PrintWriter out = resp.getWriter();

            String xmlInput = req.getParameter("xml");
            String xsltInput = req.getParameter("xslt");

            out.println("<!DOCTYPE html>");
            out.println("<html><head><title>Axis - Transformation Result</title>");
            out.println("<style>");
            out.println("body { font-family: monospace; background: #1a1a2e; color: #e0e0e0; margin: 40px; }");
            out.println("h1 { color: #00d4ff; }");
            out.println("pre { background: #0d0d1a; padding: 20px; border: 1px solid #333; white-space: pre-wrap; color: #00ff88; }");
            out.println(".error { color: #ff4444; }");
            out.println("a { color: #00d4ff; }");
            out.println("</style></head><body>");
            out.println("<h1>Transformation Result</h1>");
            out.println("<a href='/'>Back</a><br/><br/>");

            if (xmlInput == null || xmlInput.isEmpty() || xsltInput == null || xsltInput.isEmpty()) {
                out.println("<p class='error'>Error: Both XML and XSLT inputs are required.</p>");
                out.println("</body></html>");
                return;
            }

            try {
                // default factory settings
                TransformerFactory factory = TransformerFactory.newInstance();

                Source xsltSource = new StreamSource(new StringReader(xsltInput));
                Transformer transformer = factory.newTransformer(xsltSource);

                Source xmlSource = new StreamSource(new StringReader(xmlInput));
                StringWriter resultWriter = new StringWriter();
                Result result = new StreamResult(resultWriter);

                transformer.transform(xmlSource, result);

                String output = resultWriter.toString();
                out.println("<h2>Output:</h2>");
                out.println("<pre>" + escapeHtml(output) + "</pre>");

            } catch (TransformerException e) {
                out.println("<h2>Transformation Error:</h2>");
                out.println("<pre class='error'>" + escapeHtml(e.getMessage()) + "</pre>");
            }

            out.println("</body></html>");
        }

        private String escapeHtml(String input) {
            if (input == null) return "";
            return input
                    .replace("&", "&amp;")
                    .replace("<", "&lt;")
                    .replace(">", "&gt;")
                    .replace("\"", "&quot;");
        }
    }
}
