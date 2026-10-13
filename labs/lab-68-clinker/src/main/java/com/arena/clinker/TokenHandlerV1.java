package com.arena.clinker;

import java.util.Base64;

public class TokenHandlerV1 {

    public static String createToken(int a, String b) {
        long ts = System.currentTimeMillis() / 1000;
        String p = a + ":" + b + ":" + ts;
        return Base64.getEncoder().encodeToString(p.getBytes());
    }

    public static SessionInfo parseToken(String t) {
        if (t == null || t.length() < 8) return null;
        if (!t.matches("[A-Za-z0-9+/=]+")) return null;
        try {
            String d = new String(Base64.getDecoder().decode(t));
            String[] z = d.split(":");
            if (z.length != 3) return null;
            int i = Integer.parseInt(z[0]);
            String r = z[1];
            long ts = Long.parseLong(z[2]);
            return new SessionInfo(i, r, ts);
        } catch (Exception e) {
            return null;
        }
    }

    public static class SessionInfo {
        public final int userId;
        public final String role;
        public final long timestamp;

        public SessionInfo(int a, String b, long c) {
            this.userId = a;
            this.role = b;
            this.timestamp = c;
        }

        public boolean isAdmin() {
            return "admin".equals(role);
        }
    }
}
