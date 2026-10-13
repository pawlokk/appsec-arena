package com.arena.seam;

import java.io.FileOutputStream;
import java.io.IOException;

public class DataExport {
    public static void writeBytesToFilename(String path, byte[] data) {
        try (FileOutputStream o = new FileOutputStream(path)) {
            o.write(data);
        } catch (IOException e) {
            throw new RuntimeException(e);
        }
    }
}
