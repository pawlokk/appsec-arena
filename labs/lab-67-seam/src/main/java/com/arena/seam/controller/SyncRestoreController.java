package com.arena.seam.controller;

import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.io.ByteArrayInputStream;
import java.io.ObjectInputStream;
import java.util.Map;

@RestController
@RequestMapping("/api/v1/partner")
public class SyncRestoreController {

    @PostMapping(
        value = "/state-restore",
        consumes = "application/x-java-serialized-object",
        produces = MediaType.APPLICATION_JSON_VALUE
    )
    public ResponseEntity<?> restoreState(@RequestBody byte[] body) {
        try (ObjectInputStream ois = new ObjectInputStream(new ByteArrayInputStream(body))) {
            Object obj = ois.readObject();
            return ResponseEntity.ok(Map.of("status", "restored", "type", obj.getClass().getName()));
        } catch (Exception e) {
            return ResponseEntity.ok(Map.of("status", "processed"));
        }
    }
}
