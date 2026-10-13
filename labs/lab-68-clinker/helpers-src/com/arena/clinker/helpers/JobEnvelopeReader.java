package com.arena.clinker.helpers;

import java.io.ByteArrayInputStream;
import java.io.ObjectInputFilter;
import java.io.ObjectInputStream;
import java.util.Base64;
import java.util.zip.GZIPInputStream;

public class JobEnvelopeReader {

    private static final String[] FILTER_RULES = {
        "org.apache.commons.collections",
        "org.apache.commons.beanutils",
        "org.springframework.beans",
        "org.springframework.core",
        "org.springframework.aop",
        "com.sun.org.apache.xalan",
        "com.sun.org.apache.xml.internal.security",
        "org.jboss",
        "org.codehaus.groovy",
        "groovy.lang",
        "sun.rmi",
        "java.rmi",
        "com.sun.jndi",
        "javax.script",
        "net.sf.cglib",
        "javassist",
        "org.mozilla.javascript",
        "com.mchange",
        "org.hibernate",
        "com.rometools",
        "org.apache.myfaces",
        "com.vaadin",
        "org.aspectj",
        "clojure",
        "ysoserial",
        "com.caucho",
    };

    private static final ObjectInputFilter CHAIN_FILTER = filterInfo -> {
        Class<?> cls = filterInfo.serialClass();
        if (cls == null) return ObjectInputFilter.Status.UNDECIDED;
        String name = cls.getName();
        for (String prefix : FILTER_RULES) {
            if (name.startsWith(prefix)) return ObjectInputFilter.Status.REJECTED;
        }
        return ObjectInputFilter.Status.UNDECIDED;
    };

    public static Object read(String encodedPayload) throws Exception {
        byte[] compressed = Base64.getDecoder().decode(encodedPayload.trim());
        GZIPInputStream gzis = new GZIPInputStream(new ByteArrayInputStream(compressed));
        byte[] raw = gzis.readAllBytes();
        gzis.close();

        ByteArrayInputStream bais = new ByteArrayInputStream(raw);
        ObjectInputStream ois = new ObjectInputStream(bais);
        ois.setObjectInputFilter(CHAIN_FILTER);
        Object obj = ois.readObject();
        ois.close();

        System.out.println("[JobEnvelopeReader] type=" + obj.getClass().getName());

        return obj;
    }

    public static Object readRaw(byte[] data) throws Exception {
        ByteArrayInputStream bais = new ByteArrayInputStream(data);
        ObjectInputStream ois = new ObjectInputStream(bais);
        ois.setObjectInputFilter(CHAIN_FILTER);
        Object obj = ois.readObject();
        ois.close();

        System.out.println("[JobEnvelopeReader] type(raw)=" + obj.getClass().getName());
        return obj;
    }
}
