package com.arena.clinker;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.Base64;
import java.util.UUID;

public class TokenHandlerV2 {

    private static final String SECRET = System.getenv().getOrDefault(
        "SESSION_SECRET", UUID.randomUUID().toString()
    );

    public static String create(int a, String b) {
        String p = a + ":" + b + ":" + System.currentTimeMillis();
        String s = hs(p, SECRET);
        String m = p + "." + s;
        return Base64.getEncoder().encodeToString(m.getBytes(StandardCharsets.UTF_8));
    }

    public static TokenHandlerV1.SessionInfo verify(String t) {
        if (t == null) return null;
        try {
            String d = new String(Base64.getDecoder().decode(t), StandardCharsets.UTF_8);
            int x = d.lastIndexOf('.');
            if (x < 0) return null;
            String p = d.substring(0, x);
            String g = d.substring(x + 1);
            if (!ce(g, hs(p, SECRET))) return null;
            String[] z = p.split(":");
            if (z.length != 3) return null;
            int i = Integer.parseInt(z[0]);
            String r = z[1];
            long ts = Long.parseLong(z[2]);
            return new TokenHandlerV1.SessionInfo(i, r, ts);
        } catch (Exception e) {
            return null;
        }
    }

    private static String hs(String a, String b) {
        try {
            Mac m = Mac.getInstance("HmacSHA256");
            m.init(new SecretKeySpec(b.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
            return Base64.getEncoder().encodeToString(m.doFinal(a.getBytes(StandardCharsets.UTF_8)));
        } catch (Exception e) {
            throw new RuntimeException("HMAC failed", e);
        }
    }

    private static boolean ce(String a, String b) {
        if (a.length() != b.length()) return false;
        int r = 0;
        for (int i = 0; i < a.length(); i++) r |= a.charAt(i) ^ b.charAt(i);
        return r == 0;
    }
}
