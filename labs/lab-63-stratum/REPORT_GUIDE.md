# Stratum — Lab Report Guide

Use this template to document your findings. Fill in each section as you work through the lab.
Do not skip the source-to-sink trace — it is required for both objectives.

---

## Objective 1: FLAG_LOCAL

**Access gained:** (describe what access level you achieved and how)

**Entry point:** (file and route where the issue begins)

**Data flow:** (trace the input from entry point through intermediate layers to the sink)

**Sink:** (the function or operation that produces the outcome)

**Observation:** (what you observed in the running application that confirmed the issue)

---

## Objective 2: FLAG_PROOF

**Entry point:** (file and route where the issue begins)

**Data flow:** (trace the input through at least three layers)

**Sink:** (the function or operation that produces code execution)

**Evidence:** (command output, file read, or other observable confirmation)

---

## Rabbit holes encountered

(List any paths you investigated that turned out to be dead ends or insufficient. Note why each was not exploitable.)

---

## Filters and mitigations observed

(List each filter or validation you encountered. Note which were effective, which were bypassable, and which were irrelevant to the actual path.)

---

## Patch recommendation

(Describe in general terms what fix would close each issue. Do not include working exploit code.)
