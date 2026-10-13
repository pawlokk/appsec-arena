package com.arena.seam.controller;

import org.springframework.http.ResponseEntity;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.*;

import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import java.io.ByteArrayInputStream;
import java.util.Map;

@RestController
@RequestMapping("/api/v1/accounts")
public class AccountRestController {

    private final JdbcTemplate jdbc;

    public AccountRestController(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    @PostMapping(consumes = "application/xml")
    public ResponseEntity<?> createAccount(@RequestBody String b, Authentication auth) throws Exception {
        DocumentBuilderFactory dbf = DocumentBuilderFactory.newInstance();
        DocumentBuilder db = dbf.newDocumentBuilder();
        org.w3c.dom.Document d = db.parse(new ByteArrayInputStream(b.getBytes("UTF-8")));

        String fn = gc(d, "firstName");
        String ln = gc(d, "lastName");

        var r = jdbc.queryForList("SELECT id FROM users WHERE username = ?", auth.getName());
        Integer oi = r.isEmpty() ? null : (Integer) r.get(0).get("id");

        jdbc.update("INSERT INTO accounts (first_name, last_name, owner_id) VALUES (?, ?, ?)",
                fn, ln, oi);

        return ResponseEntity.ok(Map.of("status", "created", "firstName", fn, "lastName", ln));
    }

    private String gc(org.w3c.dom.Document d, String n) {
        var nl = d.getElementsByTagName(n);
        if (nl.getLength() == 0) return "";
        return nl.item(0).getTextContent();
    }
}
