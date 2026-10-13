# Loam — Lab Report Guide

## Objective 1: FLAG_LOCAL

**Access gained:**

**Entry point:**

**Authorization gap:** (which endpoint? which ownership check was missing?)

**Authorization gap:** (how did you access a resource belonging to another tenant?)

**Sink:**

---

## Objective 2: FLAG_PROOF

**Second-order chain:**
1. Which endpoint stored the malicious payload?
2. Which table was it stored in?
3. Which background component processed it? Which method?
4. What serialization settings were used by the worker?

**Serialization discrepancy:** (what setting is used at the controller layer vs the worker layer?)

**Payload type used:**

**Trigger timing:** (how long until TaskProcessor ran? how did you observe it?)

**Sink:**

**Evidence:**

---

## Token generation analysis

**Partial source location:** (`Helpers/TokenHelper.cs.partial`)

**Algorithm shown in partial source:**

**Algorithm found in compiled component:** (examine `Helpers/TokenHelper.dll`)

**Discrepancy:** (what's different? is the partial source accurate?)

---

## Query behavior analysis (if used)

**Endpoint:**

**Timing primitive used:**

**Data extracted:**

---

## Rabbit holes encountered

---

## Filters observed

---

## Patch recommendation
