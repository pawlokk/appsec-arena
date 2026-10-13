# Lab 16 — TUDO (bmdyy)

## Description

TUDO is a deliberately vulnerable PHP web application simulating a multi-user content management system. It features user authentication, role-based access control (regular users and admin), and database-backed functionality including posts and images. Designed as a white-box exploitation practice target, the attacker must bypass authentication, escalate to admin, and achieve remote code execution.

Source: https://github.com/bmdyy/tudo

## Stack

- PHP 8.1, Apache
- PostgreSQL
- Docker / Docker Compose
- Xdebug

## Vulnerabilities

- Authentication Bypass (2 methods)
- Privilege Escalation to Admin
- SQL Injection
- Remote Code Execution (5 methods)
- Server-Side Template Injection (SSTI)
- Insecure Direct Object Reference (IDOR)

## Access

- URL: http://localhost:5116
- Default credentials:
  - user1 / user1
  - user2 / user2
  - admin / admin

## Challenge Objectives

1. **Authentication Bypass** — Access user1 or user2 account (two methods)
2. **Privilege Escalation** — Obtain admin privileges
3. **Remote Code Execution** — Execute arbitrary OS commands (five methods)
