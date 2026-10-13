package com.arena.seam;

import org.springframework.web.bind.annotation.*;
import javax.sql.DataSource;
import java.sql.*;

@RestController
@RequestMapping("/api/v2")
public class ResourceSyncController {

    private final DataSource dataSource;

    public ResourceSyncController(DataSource dataSource) {
        this.dataSource = dataSource;
    }

    @GetMapping("/resources/sync")
    @ResponseBody
    public String sync(
            @RequestParam(required = false) String syncMode,
            @RequestParam(required = false) String rangeStart,
            @RequestParam(required = false) String userId,
            @RequestParam(required = false) String restrictedCheck) {

        StringBuilder o = new StringBuilder();

        if (syncMode != null && "full".equals(syncMode)) {
            return far();
        }

        if (restrictedCheck != null && "true".equals(restrictedCheck)) {
            return cra(userId);
        }

        if (rangeStart != null) {
            return (userId != null && !userId.isEmpty()) ? fru(userId, rangeStart, o) : frr(rangeStart, o);
        }

        return "";
    }

    private String fru(String userId, String rangeStart, StringBuilder o) {
        int st;
        try { st = Integer.parseInt(rangeStart); }
        catch (NumberFormatException e) { return "<error>invalid range</error>"; }
        int en = st + 10000;

        String fi = userId
            .replace("'", "&#39;")
            .replace("\"", "&#34;");

        String q = "select distinct(id) from reports where owner_id=" + fi
                   + " and id > " + st + " and id < " + en;

        try (Connection c = dataSource.getConnection();
             Statement s = c.createStatement()) {
            ResultSet r = s.executeQuery(q);
            while (r.next()) {
                o.append(r.getString(1)).append("\n");
            }
        } catch (Exception ex) {
            o.append(ex.getMessage());
        }

        return o.toString();
    }

    private String frr(String rangeStart, StringBuilder o) {
        int st;
        try { st = Integer.parseInt(rangeStart); }
        catch (NumberFormatException e) { return "<error>invalid range</error>"; }
        int en = st + 10000;

        try (Connection c = dataSource.getConnection();
             PreparedStatement s = c.prepareStatement(
                 "select distinct(id) from reports where id > ? and id < ?")) {
            s.setInt(1, st);
            s.setInt(2, en);
            ResultSet r = s.executeQuery();
            while (r.next()) {
                o.append(r.getString(1)).append("\n");
            }
        } catch (Exception ex) {
            o.append(ex.getMessage());
        }
        return o.toString();
    }

    private String cra(String userId) {
        if (userId == null) return "false";
        try (Connection c = dataSource.getConnection();
             PreparedStatement s = c.prepareStatement(
                 "select role from users where id = ?")) {
            s.setInt(1, Integer.parseInt(userId));
            ResultSet r = s.executeQuery();
            if (r.next()) return String.valueOf("admin".equals(r.getString(1)));
        } catch (Exception ex) { }
        return "false";
    }

    private String far() {
        StringBuilder o = new StringBuilder();
        try (Connection c = dataSource.getConnection();
             PreparedStatement s = c.prepareStatement(
                 "select id, title, category from reports order by id limit 50")) {
            ResultSet r = s.executeQuery();
            while (r.next()) {
                o.append(r.getString(1)).append("|")
                   .append(r.getString(2)).append("|")
                   .append(r.getString(3)).append("\n");
            }
        } catch (Exception ex) { o.append(ex.getMessage()); }
        return o.toString();
    }
}
