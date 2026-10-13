package com.arena.clinker;

import com.arena.clinker.helpers.JobEnvelopeReader;

import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import java.io.IOException;
import java.io.PrintWriter;

public class ArchiveBatchServlet extends HttpServlet {

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/json");
        PrintWriter out = resp.getWriter();

        try {
            String encoded = new String(req.getInputStream().readAllBytes()).trim();
            Object result = JobEnvelopeReader.read(encoded);
            out.println("{\"status\":\"ok\",\"type\":\"" + result.getClass().getSimpleName() + "\"}");
        } catch (Exception e) {
            resp.setStatus(500);
            out.println("{\"error\":\"" + e.getMessage() + "\"}");
        }
    }
}
