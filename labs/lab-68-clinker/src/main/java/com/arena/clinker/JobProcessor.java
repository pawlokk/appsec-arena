package com.arena.clinker;

import com.arena.clinker.helpers.JobEnvelopeReader;

import java.sql.*;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class JobProcessor {

    private final ScheduledExecutorService scheduler = Executors.newSingleThreadScheduledExecutor();
    private final long intervalMs;

    public JobProcessor(long intervalMs) {
        this.intervalMs = intervalMs;
    }

    public void start() {
        scheduler.scheduleWithFixedDelay(this::processPending, 5, intervalMs / 1000, TimeUnit.SECONDS);
        System.out.println("[JobProcessor] started, interval=" + intervalMs + "ms");
    }

    private void processPending() {
        try (Connection conn = Database.getConnection()) {
            String select = "SELECT id, payload FROM document_jobs WHERE status = 'pending' ORDER BY created_at LIMIT 5";
            PreparedStatement ps = conn.prepareStatement(select);
            ResultSet rs = ps.executeQuery();
            while (rs.next()) {
                int jobId = rs.getInt(1);
                String payload = rs.getString(2);
                processJob(conn, jobId, payload);
            }
        } catch (Exception e) {
            System.err.println("[JobProcessor] error polling jobs: " + e.getMessage());
        }
    }

    private void processJob(Connection conn, int jobId, String payload) {
        String result;
        String status;
        try {
            Object obj = JobEnvelopeReader.read(payload);
            result = "processed: " + obj.getClass().getSimpleName();
            status = "completed";
        } catch (Exception e) {
            result = "error: " + e.getClass().getSimpleName() + ": " + e.getMessage();
            status = "failed";
        }
        try {
            String update = "UPDATE document_jobs SET status = ?, result = ?, processed_at = NOW() WHERE id = ?";
            PreparedStatement ps = conn.prepareStatement(update);
            ps.setString(1, status);
            ps.setString(2, result);
            ps.setInt(3, jobId);
            ps.executeUpdate();
        } catch (Exception e) {
            System.err.println("[JobProcessor] failed to update job " + jobId + ": " + e.getMessage());
        }
    }
}
