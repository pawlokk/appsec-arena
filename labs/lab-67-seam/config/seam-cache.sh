#!/bin/bash
java -cp /app/h2.jar org.h2.tools.Shell \
  -url "jdbc:h2:tcp://127.0.0.1:9001/./seam_cache" \
  -user sa \
  -password manager99
