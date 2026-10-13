# Clinker — Lab Report Guide

## Objective 1: FLAG_LOCAL

**Access gained:**

**Entry point:**

**Token analysis:** (where is the token generated? what class/method handles it?)

**Predictability:** (what makes the token reproducible? what window does the attacker have?)

**Sink:**

---

## Objective 2: FLAG_PROOF

**Entry point:** (which endpoint accepts the job payload?)

**Data flow:** (trace through at least three layers)

**Compiled component role:** (what is the role of the included JAR? which class/method is relevant?)

**Second-order timing:** (when does the job execute? how did you confirm execution?)

**Payload type used:**

**Sink:**

**Evidence:**

---

## URL validation analysis

**Validation method:** (which class performs validation?)

**Fetch method:** (which class handles the HTTP request?)

**Discrepancy observed:** (what does each handle differently?)

---

## Second-order behavior observed

(Describe the JobProcessor polling interval, trigger mechanism, and observable side-effects.)

---

## Rabbit holes encountered

---

## Filters observed

---

## Patch recommendation

---

## Extra Mile: Automating the Full Reset Chain Including Alert Deletion

**Token enumeration approach:**

**How you identified the alert was generated:** (endpoint, response structure)

**Alert deletion endpoint:**

**Full automated chain steps:**

**Evidence that alerts were cleared after exploit:**
