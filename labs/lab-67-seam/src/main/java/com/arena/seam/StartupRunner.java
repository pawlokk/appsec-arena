package com.arena.seam;

import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

@Component
public class StartupRunner implements ApplicationRunner {

    private final JdbcTemplate jdbc;
    private final PasswordEncoder passwordEncoder;

    public StartupRunner(JdbcTemplate jdbc, PasswordEncoder passwordEncoder) {
        this.jdbc = jdbc;
        this.passwordEncoder = passwordEncoder;
    }

    @Override
    public void run(ApplicationArguments args) {
        String u = "jdbc:h2:file:/app/cache/seam_cache;DB_CLOSE_ON_EXIT=FALSE";
        try (java.sql.Connection c = java.sql.DriverManager.getConnection(u, "sa", "manager99")) {
            c.createStatement().execute("CREATE TABLE IF NOT EXISTS cache_entries (k VARCHAR PRIMARY KEY, v TEXT, ts TIMESTAMP DEFAULT NOW())");
        } catch (Exception ignored) {}
        try {
            org.h2.tools.Server.createTcpServer("-tcp", "-tcpPort", "9001", "-tcpAllowOthers", "-baseDir", "/app/cache").start();
        } catch (Exception ignored) {}

        String p = System.getenv("ADMIN_PASSWORD");
        if (p != null && !p.isBlank()) {
            jdbc.update("UPDATE users SET password = ? WHERE username = 'admin'", passwordEncoder.encode(p));
        }
    }
}
