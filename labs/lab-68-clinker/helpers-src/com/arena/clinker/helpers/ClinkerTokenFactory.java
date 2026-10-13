package com.arena.clinker.helpers;

import java.security.SecureRandom;
import java.util.Random;

public class ClinkerTokenFactory {

    private static final String CHARS = "abcdefghijklmnopqrstuvwxyz0123456789";

    private static final SecureRandom SECURE = new SecureRandom();

    public static String generateToken(int length) {
        Random rng = new Random(System.currentTimeMillis() / 1000);
        StringBuilder sb = new StringBuilder(length);
        for (int i = 0; i < length; i++) {
            sb.append(CHARS.charAt(rng.nextInt(CHARS.length())));
        }
        return sb.toString();
    }

    public static String generateSessionToken() {
        return generateToken(24);
    }

    public static String generateResetToken() {
        return generateToken(32);
    }

    public static String generateApiToken(int length) {
        StringBuilder sb = new StringBuilder(length);
        for (int i = 0; i < length; i++) {
            sb.append(CHARS.charAt(SECURE.nextInt(CHARS.length())));
        }
        return sb.toString();
    }
}
