package com.arena.seam.controller;

import com.arena.seam.dto.PartnerProjectXml;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.*;

import java.util.Map;

@RestController
@RequestMapping("/api/v1/partner")
public class PartnerApiController {

    private final JdbcTemplate jdbc;

    public PartnerApiController(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    @PostMapping(
        value = "/projects",
        consumes = MediaType.APPLICATION_XML_VALUE,
        produces = MediaType.APPLICATION_JSON_VALUE
    )
    public ResponseEntity<?> createProjectXml(
            @RequestBody PartnerProjectXml request,
            Authentication auth) {

        String username = auth.getName();
        Long userId;
        try {
            userId = jdbc.queryForObject(
                "SELECT id FROM users WHERE username = ?", Long.class, username
            );
        } catch (Exception e) {
            return ResponseEntity.status(401).body(Map.of("error", "user not found"));
        }

        if (request.getName() == null || request.getName().isBlank()) {
            return ResponseEntity.badRequest().body(Map.of("error", "name required"));
        }

        jdbc.update(
            "INSERT INTO projects (name, description, owner_id, tenant_id) VALUES (?, ?, ?, ?)",
            request.getName(), request.getDescription(), userId, request.getTenantId()
        );

        return ResponseEntity.ok(Map.of("status", "created", "name", request.getName()));
    }

    @GetMapping(value = "/projects", produces = MediaType.APPLICATION_XML_VALUE)
    public ResponseEntity<?> listProjectsXml(Authentication auth) {
        String username = auth.getName();
        Long userId;
        try {
            userId = jdbc.queryForObject(
                "SELECT id FROM users WHERE username = ?", Long.class, username
            );
        } catch (Exception e) {
            return ResponseEntity.status(401).body("<error>user not found</error>");
        }

        var projects = jdbc.queryForList(
            "SELECT id, name, description, tenant_id FROM projects WHERE owner_id = ?", userId
        );

        StringBuilder xml = new StringBuilder("<projects>");
        for (var p : projects) {
            xml.append("<project>")
               .append("<id>").append(p.get("id")).append("</id>")
               .append("<name>").append(p.get("name")).append("</name>")
               .append("</project>");
        }
        xml.append("</projects>");

        return ResponseEntity.ok()
            .contentType(MediaType.APPLICATION_XML)
            .body(xml.toString());
    }
}
