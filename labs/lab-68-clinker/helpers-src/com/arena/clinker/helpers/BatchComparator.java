package com.arena.clinker.helpers;

import java.io.Serializable;
import java.util.Comparator;

public class BatchComparator implements Comparator<Object>, Serializable {
    private static final long serialVersionUID = 1L;

    @Override
    public int compare(Object a, Object b) {
        return a.toString().compareTo(b.toString());
    }
}
