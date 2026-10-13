"""
AppSec Arena — Platform
Controls lab lifecycle (start / stop / reset / flag verify).
Talks to Docker daemon directly.

Port architecture
-----------------
Every lab container exposes a single standardised internal port: 8080.
The mapping to a host port is entirely owned by this platform — Dockerfiles
know nothing about host-side port numbers.

Host ports are stored in platform/port_registry.json and auto-assigned on
first startup (base 5100, incrementing).  Once assigned a port never changes,
so bookmarks and copy-link URLs remain stable across restarts.  New labs are
assigned the next free port automatically — no manual coordination needed.

To pre-assign a port, add an entry to port_registry.json before starting:
  { "lab-99-mylab": 5199 }
The platform will honour it and skip that port when assigning others.
"""

import os, secrets, hashlib, json, time, threading
from flask import Flask, render_template, jsonify, request, abort, Response, stream_with_context
import docker

app = Flask(__name__)
app.secret_key = os.environ.get("ARENA_SECRET", "changeme")

client = docker.from_env()

# Per-lab abort events — set to cancel an in-progress build
_build_abort: dict[str, threading.Event] = {}

# ── Port registry ──────────────────────────────────────────────────────────────

_PORT_BASE   = 5100
_PORT_RANGE  = 500   # allocates up to 500 labs: 5100–5599
_REGISTRY_PATH = os.path.join(os.path.dirname(__file__), "port_registry.json")

def _load_registry() -> dict:
    try:
        with open(_REGISTRY_PATH) as f:
            return json.load(f)
    except (FileNotFoundError, json.JSONDecodeError):
        return {}

def _save_registry(reg: dict) -> None:
    with open(_REGISTRY_PATH, "w") as f:
        json.dump(reg, f, indent=2, sort_keys=True)

def _assign_ports(labs: list) -> None:
    """
    Populate lab["port"] for every lab.
    Uses port_registry.json as the source of truth; auto-assigns any labs
    not yet present, choosing the next free port in [PORT_BASE, PORT_BASE+PORT_RANGE).
    Persists any new assignments so they survive restarts.
    """
    reg = _load_registry()
    used = set(reg.values())
    changed = False
    _next = _PORT_BASE

    for lab in labs:
        lid = lab["id"]
        if lid in reg:
            lab["port"] = reg[lid]
        else:
            # find next free slot
            while _next in used:
                _next += 1
            if _next >= _PORT_BASE + _PORT_RANGE:
                raise RuntimeError(f"Port pool exhausted — increase _PORT_RANGE (currently {_PORT_RANGE})")
            reg[lid] = _next
            lab["port"] = _next
            used.add(_next)
            _next += 1
            changed = True

    if changed:
        _save_registry(reg)

# ── Network ────────────────────────────────────────────────────────────────────

def _ensure_network():
    try:
        client.networks.get("appsec-arena")
    except docker.errors.NotFound:
        client.networks.create("appsec-arena", driver="bridge")

_ensure_network()

LABS = [
    {
        "id": "lab-00-testr",
        "name": "Intro",
        "level": 0,
        "lang": "PHP",
        "stack": "PHP 8 · Apache",
        "tags": ["Auth Bypass", "Command Injection", "RCE"],
        "description": "Introductory lab. Hardcoded admin:admin credentials expose FLAG_LOCAL after login. Authenticated command executor demonstrates server-side RCE — run any OS command to read FLAG_PROOF.",
        "image": "appsec-arena-lab-00-testr",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-01-python",
        "name": "Serpentine",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · SQLite",
        "tags": ["Auth Bypass", "SSTI", "RCE"],
        "description": "Hardcoded JWT secret → session forge → Jinja2 SSTI to RCE.",
        "image": "appsec-arena-lab-01-python",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-02-php",
        "name": "Labyrinth",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP 8 · MySQL",
        "tags": ["SQLi", "Auth Bypass", "File Upload", "RCE"],
        "description": "SQL injection auth bypass → unrestricted file upload → webshell RCE.",
        "image": "appsec-arena-lab-02-php",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-03-java",
        "name": "Crucible",
        "level": 2,
        "lang": "Java",
        "stack": "Spring Boot · H2",
        "tags": ["JWT", "Auth Bypass", "Deserialization", "RCE"],
        "description": "Weak JWT signing (none alg) → auth bypass → Java deserialization RCE.",
        "image": "appsec-arena-lab-03-java",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-04-node",
        "name": "Axiom",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express · MongoDB",
        "tags": ["NoSQLi", "Auth Bypass", "SSTI", "RCE"],
        "description": "NoSQL injection auth bypass → EJS server-side template injection to RCE.",
        "image": "appsec-arena-lab-04-node",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-05-dotnet",
        "name": "Cipher",
        "level": 2,
        "lang": ".NET",
        "stack": "ASP.NET Core · SQLite",
        "tags": ["Cookie Tamper", "Auth Bypass", "Deserialization", "RCE"],
        "description": "Predictable cookie HMAC → privilege escalation → .NET deserialization RCE.",
        "image": "appsec-arena-lab-05-dotnet",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-06-php-typejuggling",
        "name": "Parity",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP 8 · SQLite",
        "tags": ["Type Juggling", "LFI", "RCE"],
        "description": "PHP loose comparison auth bypass and local file inclusion to achieve remote code execution.",
        "image": "appsec-arena-lab-06-php-typejuggling",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-07-python-yamlrce",
        "name": "Vantage",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · SQLite",
        "tags": ["Predictable Token", "Account Takeover", "YAML Injection", "RCE"],
        "description": "Weak password reset token generation leads to account takeover. Unsafe YAML deserialisation enables RCE.",
        "image": "appsec-arena-lab-07-python-yamlrce",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-08-java-xxe",
        "name": "Aperture",
        "level": 2,
        "lang": "Java",
        "stack": "Spring Boot",
        "tags": ["XXE", "File Read", "SpEL Injection", "RCE"],
        "description": "XXE in XML login reads internal secrets for auth bypass. SpEL injection in the admin API enables RCE.",
        "image": "appsec-arena-lab-08-java-xxe",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-09-node-prototype",
        "name": "Hollow",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express",
        "tags": ["Prototype Pollution", "Auth Bypass", "Command Injection", "RCE"],
        "description": "Prototype pollution via unsafe deep merge bypasses admin checks. Command injection in diagnostics enables RCE.",
        "image": "appsec-arena-lab-09-node-prototype",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-10-php-twig",
        "name": "Relay",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP 8 · Twig · SQLite",
        "tags": ["SQLi", "Auth Bypass", "SSTI", "RCE"],
        "description": "SQL injection authentication bypass followed by Twig server-side template injection for RCE.",
        "image": "appsec-arena-lab-10-php-twig",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-18-php-deserialize",
        "name": "Phantom",
        "level": 1,
        "lang": "PHP",
        "stack": "PHP 8 · SQLite",
        "tags": ["Deserialization", "Object Injection", "Magic Methods", "RCE"],
        "description": "PHP unserialize() cookie manipulation for auth bypass. Magic method abuse (__wakeup) in scan job objects leads to RCE via command injection.",
        "image": "appsec-arena-lab-18-php-deserialize",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-19-python-pickle",
        "name": "Conduit",
        "level": 1,
        "lang": "Python",
        "stack": "Flask",
        "tags": ["Deserialization", "Pickle", "Auth Bypass", "RCE"],
        "description": "Flask session stored as base64-encoded pickle. Role manipulation for auth bypass, then __reduce__() RCE via arbitrary pickle payload.",
        "image": "appsec-arena-lab-19-python-pickle",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-20-jwt-algconf",
        "name": "Fracture",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · JWT",
        "tags": ["JWT", "Algorithm Confusion", "Auth Bypass", "RCE"],
        "description": "RS256 JWT with public key exposed at /.well-known/jwks.json. Algorithm not pinned — sign HS256 token with the public key to forge admin claims.",
        "image": "appsec-arena-lab-20-jwt-algconf",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-21-race-condition",
        "name": "Ledger",
        "level": 3,
        "lang": "PHP",
        "stack": "PHP 8 · Apache · SQLite",
        "tags": ["SQLi", "Auth Bypass", "Race Condition", "RCE"],
        "description": "Non-atomic gift card redemption (check-use-invalidate as three separate queries). Race concurrent requests to multiply credits. SQLi login bypass for admin RCE.",
        "image": "appsec-arena-lab-21-race-condition",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-22-oauth",
        "name": "Nexus",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · OAuth2",
        "tags": ["OAuth", "Open Redirect", "Token Theft", "RCE"],
        "description": "OAuth2 provider with unvalidated redirect_uri + open redirect on the same origin. Chain: craft auth URL → leak admin code → exchange for token → admin RCE via maintenance endpoint.",
        "image": "appsec-arena-lab-22-oauth",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-23-ssrf-chain",
        "name": "Gatekeeper",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express · Node.js",
        "tags": ["SSRF", "Internal Bypass", "RCE"],
        "description": "Webhook preview feature with unvalidated URL. SSRF to internal admin on port 3001. Admin diagnostics endpoint runs shell commands.",
        "image": "appsec-arena-lab-23-ssrf-chain",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-24-host-header",
        "name": "Mirage",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · SQLite",
        "tags": ["Host Header Injection", "Password Reset", "Account Takeover", "RCE"],
        "description": "Password reset link built from request Host header. Poison the token delivery URL to capture admin's reset token. Account takeover → eval endpoint → RCE.",
        "image": "appsec-arena-lab-24-host-header",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-25-mass-assignment",
        "name": "Scaffold",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express · Node.js",
        "tags": ["Mass Assignment", "Privilege Escalation", "SSTI", "RCE"],
        "description": "User registration passes entire request body to object creation — include role:admin to escalate. Admin panel renders user-controlled bio as Handlebars template string.",
        "image": "appsec-arena-lab-25-mass-assignment",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-26-sqli-outfile",
        "name": "Riftline",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP · MySQL · Apache",
        "tags": ["SQLi", "Stacked Queries", "File Write", "Webshell", "RCE"],
        "description": "Product search vulnerable to stacked SQL injection. MySQL FILE privilege enabled — write PHP webshell to webroot via INTO OUTFILE → execute arbitrary commands.",
        "image": "appsec-arena-lab-26-sqli-outfile",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-27-stored-xss-chain",
        "name": "Ember",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express · SQLite",
        "tags": ["Stored XSS", "Cookie Theft", "Session Hijack", "RCE"],
        "description": "Review platform renders submitted content as innerHTML. Admin bot visits reviews every 10s. Stored XSS captures admin session cookie → session hijack → exec endpoint RCE.",
        "image": "appsec-arena-lab-27-stored-xss-chain",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-28-idor-secret",
        "name": "Keystone",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · SQLite",
        "tags": ["IDOR", "Secret Key Leak", "Session Forge", "RCE"],
        "description": "Profile API returns all DB fields including debug_key for any user ID. Admin's debug_key IS the Flask SECRET_KEY. Forge a signed admin session cookie → admin maintenance endpoint → RCE.",
        "image": "appsec-arena-lab-28-idor-secret",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-29-log-poison",
        "name": "Residue",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP · Apache",
        "tags": ["Path Traversal", "LFI", "Log Poisoning", "RCE"],
        "description": "File viewer parameter traverses to /var/log/apache2/access.log. Inject PHP payload in User-Agent header. LFI to include the poisoned log → code execution.",
        "image": "appsec-arena-lab-29-log-poison",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-30-jwt-kid-sqli",
        "name": "Crowbar",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · SQLite · JWT",
        "tags": ["JWT", "kid Injection", "SQLi", "Token Forge", "RCE"],
        "description": "JWT verification fetches signing secret from SQLite using kid header field via raw string concat. Inject SQL to return attacker-controlled secret → forge admin token → exec endpoint RCE.",
        "image": "appsec-arena-lab-30-jwt-kid-sqli",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-31-graphql-bola",
        "name": "Schema",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · GraphQL · SQLite",
        "tags": ["GraphQL", "Introspection", "BOLA", "Data Exfil", "RCE"],
        "description": "GraphQL API with introspection enabled. Discover hidden runDiagnostic mutation. BOLA in user query leaks admin API key. Use key to trigger command execution mutation.",
        "image": "appsec-arena-lab-31-graphql-bola",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-32-second-order-sqli",
        "name": "Residuum",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · MySQL",
        "tags": ["Second-Order SQLi", "Stored Injection", "Auth Bypass", "RCE"],
        "description": "Username stored safely on registration but used raw in admin report query. Register with SQLi payload as username — fires when admin report runs, reads flag via LOAD_FILE.",
        "image": "appsec-arena-lab-32-second-order-sqli",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-33-eval-blocklist",
        "name": "Blocklist",
        "level": 3,
        "lang": "PHP",
        "stack": "PHP · Apache",
        "tags": ["eval()", "Blocklist Bypass", "WAF Evasion", "RCE"],
        "description": "PHP admin eval() endpoint blocked from using system/exec/shell_exec. Bypass the function name blocklist via str_rot13, ReflectionFunction->invoke, or variable function construction to achieve RCE.",
        "image": "appsec-arena-lab-33-eval-blocklist",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-34-zipslip-upload",
        "name": "Courier",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP · Apache",
        "tags": ["File Upload", "Zip Slip", "Extension Bypass", ".htaccess", "RCE"],
        "description": "Document portal validates upload extensions and MIME types. Bypass via .htaccess upload to enable PHP execution, double-extension tricks, or zip slip archive traversal to plant a .phtml webshell.",
        "image": "appsec-arena-lab-34-zipslip-upload",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-35-postgres-rce",
        "name": "Pipeline",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · PostgreSQL",
        "tags": ["SQLi", "PostgreSQL", "COPY TO PROGRAM", "Superuser", "RCE"],
        "description": "Analytics dashboard SQLi. Confirm PostgreSQL superuser, then chain to RCE via COPY TO PROGRAM or Large Object binary upload.",
        "image": "appsec-arena-lab-35-postgres-rce",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-36-java-prng",
        "name": "Clockwork",
        "level": 3,
        "lang": "Java",
        "stack": "Java · Jetty · HSQLDB",
        "tags": ["Java PRNG", "Predictable Token", "Account Takeover", "HSQL JRT", "RCE"],
        "description": "Password reset uses java.util.Random seeded from System.currentTimeMillis(). Brute-force millisecond seed from server Date header to predict admin token. Post-auth: HSQL Java Language Routines write a JSP shell via writeBytesToFilename.",
        "image": "appsec-arena-lab-36-java-prng",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-37-node-eval-bypass",
        "name": "Batch",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · Node.js",
        "tags": ["eval()", "Batch Endpoint", "Hex Encoding Bypass", "RCE"],
        "description": "REST API batch endpoint resolves cross-request references via eval(). Forward slash blocked to prevent path traversal. Bypass using \\x2f hex encoding or String.fromCharCode(47) to construct shell commands.",
        "image": "appsec-arena-lab-37-node-eval-bypass",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-38-cors-csrf",
        "name": "Accord",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · Node.js",
        "tags": ["CORS", "Session Riding", "CSRF", "Static API Key", "YAML RCE"],
        "description": "Workflow automation platform with CORS reflecting any origin with credentials. Static API key in /api/config enables admin setup. CORS session riding to CSRF-upload a YAML workflow with a malicious script step executed via Function().",
        "image": "appsec-arena-lab-38-cors-csrf",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-39-websocket-cmdi",
        "name": "Socket",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · WebSocket",
        "tags": ["DOM XSS", "WebSocket", "su -c Injection", "Command Injection", "RCE"],
        "description": "Monitoring dashboard with DOM XSS (document.write of unsanitised search param). XSS steals WebSocket auth key from localStorage. Authenticated WebSocket diagnostic commands inject through su -c double-argument shell splitting.",
        "image": "appsec-arena-lab-39-websocket-cmdi",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-40-jinja2-bypass",
        "name": "Veil",
        "level": 3,
        "lang": "Python",
        "stack": "Flask · Jinja2",
        "tags": ["SSTI", "Jinja2", "attr() Filter", "__ Bypass", "RCE"],
        "description": "Email template preview with Jinja2 SSTI. Renderer blocks double-underscores and class/mro/subclasses literals. Bypass entirely using attr() filter with string concatenation: request|attr('_'+'_class_'+'_') traverses the MRO without typing __.",
        "image": "appsec-arena-lab-40-jinja2-bypass",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-41-freemarker-ssti",
        "name": "Furnace",
        "level": 1,
        "lang": "Java",
        "stack": "Java · Jetty · Freemarker",
        "tags": ["SSTI", "Freemarker", "Template Injection", "RCE"],
        "description": "Report generator renders Freemarker templates from user input with no sandbox. Assign the Execute utility class and call it with an OS command for instant RCE.",
        "image": "appsec-arena-lab-41-freemarker-ssti",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-42-smarty-ssti",
        "name": "Mold",
        "level": 1,
        "lang": "PHP",
        "stack": "PHP · Apache · Smarty",
        "tags": ["SSTI", "Smarty", "Template Injection", "RCE"],
        "description": "Theming system passes user templates to Smarty fetch() with no security policy. PHP tag execution achieves RCE through the template engine.",
        "image": "appsec-arena-lab-42-smarty-ssti",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-43-velocity-ssti",
        "name": "Torque",
        "level": 1,
        "lang": "Java",
        "stack": "Java · Jetty · Velocity",
        "tags": ["SSTI", "Velocity", "Template Injection", "ClassLoader", "RCE"],
        "description": "Campaign preview renders Apache Velocity templates. Traverse ClassLoader to Runtime.exec() via #set directives — the standard Velocity SSTI RCE chain.",
        "image": "appsec-arena-lab-43-velocity-ssti",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-44-ognl-injection",
        "name": "Piston",
        "level": 1,
        "lang": "Java",
        "stack": "Java · Jetty · OGNL",
        "tags": ["OGNL Injection", "Expression Language", "Struts2 Pattern", "RCE"],
        "description": "Action dispatcher evaluates OGNL expressions from request parameters with no SecurityManager. @java.lang.Runtime@getRuntime().exec() → RCE. Models Struts2 S2-045.",
        "image": "appsec-arena-lab-44-ognl-injection",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-45-xslt-injection",
        "name": "Axis",
        "level": 1,
        "lang": "Java",
        "stack": "Java · Jetty · Xalan",
        "tags": ["XSLT Injection", "XML Transformation", "Java Extensions", "LFI", "RCE"],
        "description": "XML report processor accepts user-supplied XSLT stylesheets without FEATURE_SECURE_PROCESSING. Read files via document() or execute commands via Xalan java extension namespace.",
        "image": "appsec-arena-lab-45-xslt-injection",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-46-xpath-injection",
        "name": "Folio",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · lxml",
        "tags": ["XPath Injection", "Auth Bypass", "Blind Extraction", "Credential Theft"],
        "description": "Document portal authenticates against XML user store via XPath with string interpolation. Auth bypass plus boolean-based blind extraction recovers admin password char by char.",
        "image": "appsec-arena-lab-46-xpath-injection",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-47-ldap-injection",
        "name": "Lattice",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express · ldapjs",
        "tags": ["LDAP Injection", "Auth Bypass", "Filter Bypass", "RCE"],
        "description": "HR portal authenticates against in-process LDAP server. Filter built via string concat. Inject closing paren and OR clause to bypass. Admin eval() → RCE.",
        "image": "appsec-arena-lab-47-ldap-injection",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-48-imagemagick-rce",
        "name": "Darkroom",
        "level": 1,
        "lang": "Python",
        "stack": "Flask · ImageMagick",
        "tags": ["ImageMagick", "MVG Injection", "File Upload", "RCE"],
        "description": "Photo processor passes uploads directly to ImageMagick convert with permissive policy.xml. Upload MVG with label:@ to read files, or SVG with pipe metacharacter for command execution.",
        "image": "appsec-arena-lab-48-imagemagick-rce",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-49-docx-xxe",
        "name": "Envelope",
        "level": 2,
        "lang": "Python",
        "stack": "Flask · lxml",
        "tags": ["XXE", "DOCX Upload", "File Read", "SSRF", "OOB"],
        "description": "Document converter parses .docx XML with lxml external entity resolution enabled. Craft malicious word/document.xml for file read or SSRF to internal service.",
        "image": "appsec-arena-lab-49-docx-xxe",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-50-ssi-injection",
        "name": "Imprint",
        "level": 1,
        "lang": "PHP",
        "stack": "PHP · Apache · mod_include",
        "tags": ["SSI Injection", "Apache mod_include", "File Write", "RCE"],
        "description": "Static site generator writes user content to .shtml files with Apache mod_include enabled. Inject <!--#exec cmd=--> SSI directives to run OS commands via the web server.",
        "image": "appsec-arena-lab-50-ssi-injection",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-51-php-pop-chain",
        "name": "Cascade",
        "level": 2,
        "lang": "PHP",
        "stack": "PHP · Apache · SQLite",
        "tags": ["PHP Deserialization", "POP Chain", "Magic Methods", "Gadget Chain", "RCE"],
        "description": "Session cookie holds base64-encoded serialized PHP. Three autoloaded classes form a POP chain: Shuttle.__wakeup → Relay.__toString → Terminal.__call → system(). Source exposed — construct the chain manually.",
        "image": "appsec-arena-lab-51-php-pop-chain",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-52-java-gadget-chains",
        "name": "Arsenal",
        "level": 3,
        "lang": "Java",
        "stack": "Java · Jetty · Commons Collections · Spring",
        "tags": ["Java Deserialization", "Gadget Chains", "CommonsCollections", "ysoserial", "RCE"],
        "description": "ObjectInputStream endpoint deserializes raw Java from base64 POST body. CommonsCollections 3.1 and Spring Core on classpath. ysoserial served at /tools/ysoserial.jar. Select correct gadget chain and generate payload.",
        "image": "appsec-arena-lab-52-java-gadget-chains",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-53-pdf-ssrf",
        "name": "Render",
        "level": 3,
        "lang": "Python",
        "stack": "Flask · wkhtmltopdf",
        "tags": ["SSRF", "PDF Generation", "wkhtmltopdf", "JavaScript SSRF", "RCE"],
        "description": "Invoice generator converts HTML to PDF via wkhtmltopdf with JavaScript enabled. Inject script tag for server-side XHR to internal admin on port 8888. POST SSRF via JS to reach exec endpoint.",
        "image": "appsec-arena-lab-53-pdf-ssrf",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-54-nosql-regex",
        "name": "Sieve",
        "level": 2,
        "lang": "Node.js",
        "stack": "Express · NeDB",
        "tags": ["NoSQLi", "MongoDB Operators", "Regex Blind Extraction", "RCE"],
        "description": "Login passes req.body directly to NeDB findOne(). Bypass with $ne:null. Extract admin password char by char via $regex. Admin JWT unlocks RCE shell endpoint.",
        "image": "appsec-arena-lab-54-nosql-regex",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-55-blind-sqli-timing",
        "name": "Ripple",
        "level": 4,
        "lang": "Python",
        "stack": "Flask · PostgreSQL",
        "tags": ["Blind SQLi", "Time-Based", "pg_sleep", "Automation Required", "COPY TO PROGRAM", "RCE"],
        "description": "Search returns only empty results. Pure time-based blind SQLi via pg_sleep() — no output, no boolean diff, no UNION. Requires automated extraction script. Recover admin creds then COPY TO PROGRAM RCE.",
        "image": "appsec-arena-lab-55-blind-sqli-timing",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-56-waf-bypass-sqli",
        "name": "Collide",
        "level": 3,
        "lang": "Python",
        "stack": "Flask · MySQL",
        "tags": ["SQLi", "WAF Bypass", "Space Bypass", "COLLATE", "Auth Bypass", "RCE"],
        "description": "Login portal with WAF middleware blocking spaces, OR, UNION, SELECT, and comments. Bypass via /**/ space substitution, || for OR, # comments, and COLLATE for hash comparison bypass.",
        "image": "appsec-arena-lab-56-waf-bypass-sqli",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-57-multi-layer-bypass",
        "name": "Static",
        "level": 3,
        "lang": "Node.js",
        "stack": "Express · Node.js",
        "tags": ["Rate Limiting Bypass", "X-Forwarded-For", "JWT Algorithm Confusion", "Prototype Pollution", "RCE"],
        "description": "Three independent security controls each with individual bypasses: rate limiter trusts X-Forwarded-For, JWT algorithm not pinned, admin endpoint deep-merges user input triggering prototype pollution RCE.",
        "image": "appsec-arena-lab-57-multi-layer-bypass",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-58-realistic-php",
        "name": "Prism",
        "level": 3,
        "lang": "PHP",
        "stack": "PHP · Apache · SQLite",
        "tags": ["Source Review", "Red Herring", "XXE", "SSRF", "Twig SSTI", "RCE"],
        "description": "Realistic 6-route PHP app. Red herring: search has PDO-safe query despite suspicious code. Real chain: admin XML import has XXE with LIBXML_NOENT, SSRF to internal config service leaks Twig key, template injection in email preview endpoint.",
        "image": "appsec-arena-lab-58-realistic-php",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-59-hydra-chain",
        "name": "Hydra",
        "level": 4,
        "lang": "Node.js",
        "stack": "Express · NeDB · Java Service",
        "tags": ["Blind NoSQLi", "Regex Extraction", "SSRF", "Java Deserialization", "Gadget Chain", "Automation Required", "RCE"],
        "description": "Five-step chain requiring automation at each stage. Blind NoSQL regex extraction recovers API key. Key unlocks SSRF to internal Java service. Java ObjectInputStream with CommonsCollections gadget chain for RCE. ysoserial served at /tools/ysoserial.jar.",
        "image": "appsec-arena-lab-59-hydra-chain",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-60-exam-sim-flask",
        "name": "Titan",
        "level": 4,
        "lang": "Python",
        "stack": "Flask · PostgreSQL",
        "tags": ["Source Review", "Red Herring SQLi", "Second-Order SQLi", "Time-Based Blind", "COPY TO PROGRAM", "Automation Required", "RCE"],
        "description": "Full exam simulation: 8-route Flask app with realistic product/order/user models. Red herring search uses parameterized queries. Real vulnerability: second-order SQLi in admin report — username stored safely but used raw in report query. Time-based extraction then COPY TO PROGRAM.",
        "image": "appsec-arena-lab-60-exam-sim-flask",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-61-php-exam-sim",
        "name": "Solvent",
        "level": 4,
        "lang": "PHP",
        "stack": "PHP · Apache · MySQL",
        "tags": ["Source Review", "PHP Type Juggling", "Timing Oracle", "PHP Deserialization", "POP Chain", "4-Class Gadget", "RCE"],
        "description": "Realistic PHP e-commerce app. Stage 1: password reset token uses loose == comparison — type juggling bypasses token verification for admin account takeover. Stage 2: profile avatar cookie holds serialized PHP object — 4-class POP chain (Shuttle→Relay→system()) for RCE.",
        "image": "appsec-arena-lab-61-php-exam-sim",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-62-mantle",
        "name": "Mantle",
        "level": 4,
        "lang": "Python",
        "stack": "Flask · PostgreSQL",
        "tags": ["Source Review", "Token Forgery", "Second-Order SSTI", "Red Herrings", "RCE"],
        "description": "10-route project management app. Two red herrings: search looks like SQLi (parameterized), upload has full MIME+extension validation. Real chain: invite token is unsigned base64 JSON → forge with role:admin → register → admin dashboard for FLAG_LOCAL → create project with Jinja2 SSTI payload in report_template → admin report renders it → RCE for FLAG_PROOF.",
        "image": "appsec-arena-lab-62-mantle",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-63-stratum",
        "name": "Stratum",
        "level": 5,
        "lang": "Python",
        "stack": "Flask · PostgreSQL",
        "tags": ["Analytics Dashboard", "REST API", "Multi-tenant", "Report Preview", "Scheduled Exports"],
        "description": "An internal analytics platform with a SPA frontend, multi-tenant data isolation, and an admin report preview service for scheduled data exports.",
        "image": "appsec-arena-lab-63-stratum",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-64-vein",
        "name": "Vein",
        "level": 5,
        "lang": "Python",
        "stack": "Flask · PostgreSQL",
        "tags": ["Document Processing", "XML Import", "URL Fetcher", "Admin Console", "Legacy Parser"],
        "description": "A document management service with XML bulk import, an internal URL rendering tool, and a privileged admin database console for maintenance operations.",
        "image": "appsec-arena-lab-64-vein",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-65-flint",
        "name": "Flint",
        "level": 5,
        "lang": "PHP",
        "stack": "PHP · Apache · MySQL",
        "tags": ["CRM", "Password Reset", "XML Bulk Import", "Contact Management", "Role-based Access"],
        "description": "A customer relationship management platform with user authentication, password reset flow, role-based contact access, and an XML-based bulk contact importer.",
        "image": "appsec-arena-lab-65-flint",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-66-chalk",
        "name": "Chalk",
        "level": 5,
        "lang": "PHP",
        "stack": "PHP · Apache · MySQL",
        "tags": ["Company Wiki", "User Profiles", "Admin Backup", "Content Management", "Export Scheduling"],
        "description": "An internal company wiki with user profiles, article management, and administrative backup and export scheduling tools for content archival.",
        "image": "appsec-arena-lab-66-chalk",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-67-seam",
        "name": "Seam",
        "level": 5,
        "lang": "Java",
        "stack": "Spring Boot · PostgreSQL",
        "tags": ["Analytics API", "User Registration", "Report Scheduling", "DBA Export", "Partner Integration"],
        "description": "A reporting and analytics API platform with user self-registration, scheduled report generation, and a privileged database export endpoint for partner integrations.",
        "image": "appsec-arena-lab-67-seam",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-68-clinker",
        "name": "Clinker",
        "level": 5,
        "lang": "Java",
        "stack": "Java · Jetty · Servlet",
        "tags": ["Enterprise Portal", "Document Management", "URL Importer", "Batch Processing", "Partner API"],
        "description": "A legacy Java enterprise portal with document management, a URL-based content importer for partner integrations, and a batch processing API for bulk operations.",
        "image": "appsec-arena-lab-68-clinker",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-69-slag",
        "name": "Slag",
        "level": 5,
        "lang": "Node.js",
        "stack": "Express · MongoDB · Handlebars",
        "tags": ["Workspace Platform", "Settings Sync", "Notification Templates", "Cross-Origin API", "Deep Merge"],
        "description": "A shared workspace platform with cross-origin settings synchronization, a configurable notification template engine, and a deep-merge configuration API for user preferences.",
        "image": "appsec-arena-lab-69-slag",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-70-tuff",
        "name": "Tuff",
        "level": 5,
        "lang": "Node.js",
        "stack": "Express · PostgreSQL",
        "tags": ["Monitoring Platform", "Plugin System", "WebSocket Checks", "Bot Integration", "Vendor Dashboard"],
        "description": "A server monitoring platform with a plugin-based check executor, real-time WebSocket status feeds, an authenticated bot for automated checks, and a vendor-integrated dashboard component.",
        "image": "appsec-arena-lab-70-tuff",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-71-marl",
        "name": "Marl",
        "level": 5,
        "lang": ".NET",
        "stack": "ASP.NET Core · SQLite",
        "tags": ["API Gateway", "Document Management", "Cross-Origin API", "XML Processing", "Serialized Config"],
        "description": "An enterprise API gateway and document management system with cross-origin access controls, XML document processing, and a serialized configuration import mechanism for provisioning.",
        "image": "appsec-arena-lab-71-marl",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-72-loam",
        "name": "Loam",
        "level": 5,
        "lang": ".NET",
        "stack": "ASP.NET MVC · SQLite",
        "tags": ["Intranet Portal", "User Directory", "Webhook Integrations", "Config Import", "Admin Provisioning"],
        "description": "A legacy ASP.NET MVC intranet portal with user directory search, outbound webhook integrations, and a configuration import wizard for admin provisioning.",
        "image": "appsec-arena-lab-72-loam",
        "category": "oswe-prep",
        "type": "docker",
    },
    {
        "id": "lab-73-schist",
        "name": "Schist",
        "level": 5,
        "lang": "Node.js",
        "stack": "Express · PostgreSQL",
        "tags": ["Workflow Automation", "Bot Integration", "API Keys", "Process Definitions", "Cross-Origin API"],
        "description": "A workflow automation platform with cross-origin API access, an authenticated bot for task execution, and server-side YAML-driven process definitions.",
        "image": "appsec-arena-lab-73-schist",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-74-shale",
        "name": "Shale",
        "level": 5,
        "lang": "Node.js",
        "stack": "Express · Microservices",
        "tags": ["Microservice Platform", "API Gateway", "File Import", "URL Renderer", "Lua Plugins"],
        "description": "A microservice platform behind an API gateway with a file import endpoint, an internal URL rendering service, and a Lua-based configurable plugin engine.",
        "image": "appsec-arena-lab-74-shale",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-75-slate",
        "name": "Slate",
        "level": 5,
        "lang": "Node.js",
        "stack": "Express · EJS/HBS/Pug · WebSocket",
        "tags": ["Remote Desktop Gateway", "Connection Tokens", "Multi-Protocol", "Session Rendering", "Plugin Extensions"],
        "description": "A web-based remote desktop gateway supporting multiple protocols, with a token-based connection pipeline and a server-side session rendering engine.",
        "image": "appsec-arena-lab-75-slate",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
        "course_based": True,
    },
    {
        "id": "lab-76-gneiss",
        "name": "Gneiss",
        "level": 5,
        "lang": "PHP",
        "stack": "PHP 8.1 · ERP Application",
        "tags": ["ERP Platform", "Custom Attributes", "Computed Fields", "Expression Engine", "Plugin Architecture"],
        "description": "An enterprise resource platform with a custom attribute system supporting computed fields, a plugin architecture, and a sandboxed expression evaluator for dynamic field calculations.",
        "image": "appsec-arena-lab-76-gneiss",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-77-pumice",
        "name": "Pumice",
        "level": 5,
        "lang": "Node.js",
        "stack": "Node.js 18 · PostgreSQL 15",
        "tags": ["Warehouse API", "Event Pipeline", "Query Proxy", "Request Filtering", "PostgreSQL Backend"],
        "description": "A warehouse event API that proxies analytical queries to a PostgreSQL backend, fronted by a middleware layer enforcing content-based request filtering.",
        "image": "appsec-arena-lab-77-pumice",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-78-quartzite",
        "name": "Quartzite",
        "level": 5,
        "lang": "PHP",
        "stack": "PHP 7.4 · Apache · MariaDB",
        "tags": ["Learning Platform", "Member Search", "Token Auth", "Course Import", "Instructor Tools"],
        "description": "An online learning platform with a member search API, token-based authentication, and instructor-level course content import functionality.",
        "image": "appsec-arena-lab-78-quartzite",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-79-phyllite",
        "name": "Phyllite",
        "level": 5,
        "lang": "PHP",
        "stack": "PHP 7.4 · Apache · MariaDB",
        "tags": ["Learning Platform", "Account Management", "Password Recovery", "Test Import", "Instructor Tools"],
        "description": "An online learning platform with an unauthenticated account management endpoint, a password recovery flow, and instructor-level test content import functionality.",
        "image": "appsec-arena-lab-79-phyllite",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-80-basalt",
        "name": "Basalt",
        "level": 5,
        "lang": "Node.js",
        "stack": "Node.js 18 · Express",
        "tags": ["REST API", "Batch Processing", "Request Aggregation", "Cross-Request References", "Performance Layer"],
        "description": "A REST API with a batch processing endpoint that aggregates multiple sub-requests into a single call, with support for cross-request data references.",
        "image": "appsec-arena-lab-80-basalt",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
    },
    {
        "id": "lab-81-rhyolite",
        "name": "Rhyolite",
        "level": 5,
        "lang": "PHP",
        "stack": "PHP 8.2 · Apache · MariaDB · Node.js",
        "tags": ["Webmail", "SMTP", "Admin Panel", "File Attachments", "Configuration Management"],
        "description": "A webmail server appliance with SMTP inbound email, a user webmail interface, an administrative configuration panel, and file attachment handling.",
        "image": "appsec-arena-lab-81-rhyolite",
        "category": "oswe-prep",
        "type": "docker",
        "course_based": True,
        "extra_ports": {25: 2525},
    },
]

# Populate lab["port"] from registry (auto-assigns new labs, stable across restarts)
_assign_ports(LABS)

LABS_BY_ID = {l["id"]: l for l in LABS}

LABS_DIR = os.path.join(os.path.dirname(__file__), "..", "labs")

# Internal port that every lab container exposes.
# Custom labs run on this port directly.
# Community-lab wrappers use socat to forward their native port here.
_CONTAINER_PORT = 5000

# ── Network exposure ───────────────────────────────────────────────────────────
# ARENA_BIND_HOST  — interface containers bind to (default: all interfaces).
#                    Set to a specific IP to restrict exposure, e.g. 192.168.18.110
# ARENA_HOST       — hostname used in generated lab URLs (Open →, Copy link, JS).
#                    Defaults to ARENA_BIND_HOST if set, otherwise falls back to
#                    the HTTP request's Host header (works fine when accessed
#                    directly from the target machine).
#
# Quick start for a Kali subnet:
#   ARENA_HOST=192.168.18.110 python app.py
_BIND_HOST = os.environ.get("ARENA_BIND_HOST", "0.0.0.0")
_ARENA_HOST = os.environ.get("ARENA_HOST", "") or (
    _BIND_HOST if _BIND_HOST != "0.0.0.0" else ""
)


def _run_container(lab: dict) -> None:
    """Create and start a fresh lab container with correct port mapping and flags."""
    flag_local = _lab_flag(lab["id"], "local")
    flag_proof = _lab_flag(lab["id"], "proof")
    client.containers.run(
        lab["image"],
        name=_container_name(lab["id"]),
        detach=True,
        network="appsec-arena",
        ports={
            f"{_CONTAINER_PORT}/tcp": (_BIND_HOST, lab["port"]),
            **{f"{cp}/tcp": (_BIND_HOST, hp) for cp, hp in lab.get("extra_ports", {}).items()},
        },
        environment={
            "FLAG_LOCAL": flag_local,
            "FLAG_PROOF": flag_proof,
        },
        remove=False,
    )


def _container_name(lab_id: str) -> str:
    return f"arena-{lab_id}"


def _container_status(lab_id: str) -> str:
    """Return 'running' | 'stopped' | 'absent'"""
    try:
        c = client.containers.get(_container_name(lab_id))
        return c.status  # 'running', 'exited', etc.
    except docker.errors.NotFound:
        return "absent"


def _image_exists(image_tag: str) -> bool:
    try:
        client.images.get(image_tag)
        return True
    except docker.errors.ImageNotFound:
        return False


def _lab_build_path(lab_id: str) -> str:
    return os.path.abspath(os.path.join(LABS_DIR, lab_id))


def _lab_flag(lab_id: str, flag_type: str) -> str:
    """Derive a deterministic flag from lab_id + secret. flag_type: 'local'|'proof'"""
    h = hashlib.sha256(f"{app.secret_key}:{lab_id}:{flag_type}".encode()).hexdigest()[:24]
    prefix = "LOCAL" if flag_type == "local" else "PROOF"
    return f"ARENA{{{prefix}_{h}}}"


import re as _re
_FLAG_RE = _re.compile(r'ARENA\{[^}]+\}')

def _redact(line: str) -> str:
    """Strip flag values from log lines so they can't be read from the log drawer."""
    return _FLAG_RE.sub('[flag redacted]', line)


# ── Routes ─────────────────────────────────────────────────────────────────────

@app.route("/directory")
def directory():
    categories = sorted(set(l.get("category", "oswe-prep") for l in LABS))
    return render_template("directory.html", labs=LABS, categories=categories)


@app.route("/")
def index():
    statuses = {l["id"]: _container_status(l["id"]) for l in LABS}
    categories = sorted(set(l.get("category", "oswe-prep") for l in LABS))
    # ARENA_HOST env var wins; fall back to the HTTP request's Host header.
    # This ensures lab links always point to the right IP regardless of how
    # the platform itself is accessed (localhost vs LAN IP).
    host = _ARENA_HOST or request.host.split(":")[0]
    return render_template("index.html", labs=LABS, statuses=statuses, host=host, categories=categories)


@app.route("/api/labs")
def api_labs():
    result = []
    for l in LABS:
        result.append({
            **l,
            "status": _container_status(l["id"]),
            "image_built": _image_exists(l["image"]),
        })
    return jsonify(result)


@app.route("/api/lab/<lab_id>/start", methods=["POST"])
def lab_start(lab_id):
    lab = LABS_BY_ID.get(lab_id)
    if not lab:
        abort(404)

    # If image doesn't exist yet, tell the client to build first
    if not _image_exists(lab["image"]):
        return jsonify({"status": "needs_build", "port": lab["port"]}), 202

    name = _container_name(lab_id)
    try:
        c = client.containers.get(name)
        if c.status != "running":
            c.start()
        return jsonify({"status": "running", "port": lab["port"]})
    except docker.errors.NotFound:
        pass

    # Create fresh container
    _run_container(lab)
    return jsonify({"status": "running", "port": lab["port"]})


@app.route("/api/lab/<lab_id>/build/stream")
def lab_build_stream(lab_id):
    """
    SSE endpoint — streams docker build output, then starts the container.
    The client opens this when lab_start returns needs_build.
    """
    lab = LABS_BY_ID.get(lab_id)
    if not lab:
        abort(404)

    build_path = _lab_build_path(lab_id)

    # Register abort event for this lab (cancel any previous build first)
    abort_event = threading.Event()
    _build_abort[lab_id] = abort_event

    def generate():
        api = docker.APIClient(base_url="unix:///var/run/docker.sock")

        yield f"data: [arena] Building {lab['image']}...\n\n"

        try:
            for raw in api.build(
                path=build_path,
                tag=lab["image"],
                rm=True,
                decode=True,
            ):
                # Aborted — client hit Reset or Stop during build
                if abort_event.is_set():
                    yield "data: [arena] Build cancelled.\n\n"
                    yield "event: build_failed\ndata: done\n\n"
                    return

                if "stream" in raw:
                    for line in raw["stream"].splitlines():
                        if line.strip():
                            yield f"data: {_redact(line)}\n\n"
                elif "error" in raw:
                    yield f"data: [error] {_redact(raw['error'])}\n\n"
                    yield "event: build_failed\ndata: done\n\n"
                    return
                elif "status" in raw:
                    msg = raw.get("status", "")
                    prog = raw.get("progress", "")
                    if msg.strip():
                        yield f"data: {msg} {prog}\n\n"
        except Exception as e:
            yield f"data: [arena] Build error: {e}\n\n"
            yield "event: build_failed\ndata: done\n\n"
            return
        finally:
            _build_abort.pop(lab_id, None)

        if abort_event.is_set():
            yield "data: [arena] Build cancelled.\n\n"
            yield "event: build_failed\ndata: done\n\n"
            return

        yield f"data: [arena] Build complete — starting container...\n\n"

        name = _container_name(lab_id)
        try:
            c = client.containers.get(name)
            if c.status != "running":
                c.start()
        except docker.errors.NotFound:
            _run_container(lab)

        yield f"data: [arena] Container started on :{lab['port']}\n\n"
        yield "event: build_done\ndata: done\n\n"

    return Response(
        stream_with_context(generate()),
        mimetype="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",
        },
    )


@app.route("/api/lab/<lab_id>/build/cancel", methods=["POST"])
def lab_build_cancel(lab_id):
    if lab_id not in LABS_BY_ID:
        abort(404)
    ev = _build_abort.get(lab_id)
    if ev:
        ev.set()
    return jsonify({"cancelled": bool(ev)})


@app.route("/api/lab/<lab_id>/stop", methods=["POST"])
def lab_stop(lab_id):
    if lab_id not in LABS_BY_ID:
        abort(404)
    ev = _build_abort.get(lab_id)
    if ev:
        ev.set()
    try:
        c = client.containers.get(_container_name(lab_id))
        try:
            c.stop(timeout=2)
        except Exception:
            c.kill()
        return jsonify({"status": "stopped"})
    except docker.errors.NotFound:
        return jsonify({"status": "absent"})


@app.route("/api/lab/<lab_id>/reset", methods=["POST"])
def lab_reset(lab_id):
    if lab_id not in LABS_BY_ID:
        abort(404)
    # Cancel any in-progress build
    ev = _build_abort.get(lab_id)
    if ev:
        ev.set()
    name = _container_name(lab_id)
    try:
        c = client.containers.get(name)
        c.stop(timeout=5)
        c.remove(force=True)
    except docker.errors.NotFound:
        pass
    # Delegate to start — JS handles needs_build from there
    return lab_start(lab_id)


@app.route("/api/lab/<lab_id>/rebuild", methods=["POST"])
def lab_rebuild(lab_id):
    """Stop container, remove image, then rebuild from scratch."""
    if lab_id not in LABS_BY_ID:
        abort(404)
    lab = LABS_BY_ID[lab_id]
    ev = _build_abort.get(lab_id)
    if ev:
        ev.set()
    try:
        c = client.containers.get(_container_name(lab_id))
        try:
            c.stop(timeout=2)
        except Exception:
            pass
        c.remove(force=True)
    except docker.errors.NotFound:
        pass
    try:
        client.images.remove(lab["image"], force=True)
    except docker.errors.ImageNotFound:
        pass
    return lab_start(lab_id)


@app.route("/api/lab/<lab_id>/delete", methods=["POST"])
def lab_delete(lab_id):
    """Stop + remove container AND delete the built image."""
    if lab_id not in LABS_BY_ID:
        abort(404)
    lab = LABS_BY_ID[lab_id]
    ev = _build_abort.get(lab_id)
    if ev:
        ev.set()
    # Stop + remove container
    try:
        c = client.containers.get(_container_name(lab_id))
        try:
            c.stop(timeout=2)
        except Exception:
            pass
        c.remove(force=True)
    except docker.errors.NotFound:
        pass
    # Remove image
    removed_image = False
    try:
        client.images.remove(lab["image"], force=True)
        removed_image = True
    except docker.errors.ImageNotFound:
        pass
    return jsonify({"status": "deleted", "image_removed": removed_image})


@app.route("/api/lab/<lab_id>/status")
def lab_status(lab_id):
    if lab_id not in LABS_BY_ID:
        abort(404)
    return jsonify({"status": _container_status(lab_id)})


_HINTS_DIR     = os.path.join(os.path.dirname(__file__), "..", "hints")
_PROGRESS_FILE = os.path.join(os.path.dirname(__file__), "..", "progress", "hints.jsonl")

@app.route("/api/lab/<lab_id>/hint/<int:tier>", methods=["POST"])
def lab_hint(lab_id, tier):
    if lab_id not in LABS_BY_ID or tier not in (1, 2):
        abort(404)
    num = lab_id.split("-")[1]          # "lab-13-oswe-prep" → "13"
    hint_file = os.path.join(_HINTS_DIR, f"lab-{num}.json")
    if not os.path.exists(hint_file):
        return jsonify({"error": "no hints for this lab"}), 404
    with open(hint_file) as f:
        data = json.load(f)
    text = data.get(f"tier{tier}", "")
    # append to progress log
    os.makedirs(os.path.dirname(_PROGRESS_FILE), exist_ok=True)
    with open(_PROGRESS_FILE, "a") as f:
        f.write(json.dumps({"lab": lab_id, "tier": tier,
                            "ts": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())}) + "\n")
    return jsonify({"tier": tier, "text": text})


@app.route("/api/hints/log")
def hints_log():
    if not os.path.exists(_PROGRESS_FILE):
        return jsonify([])
    with open(_PROGRESS_FILE) as f:
        entries = [json.loads(l) for l in f if l.strip()]
    return jsonify(entries)


@app.route("/api/lab/<lab_id>/flag/verify", methods=["POST"])
def flag_verify(lab_id):
    if lab_id not in LABS_BY_ID:
        abort(404)
    data = request.get_json(force=True)
    submitted = (data.get("flag") or "").strip()
    for flag_type in ("local", "proof"):
        if submitted == _lab_flag(lab_id, flag_type):
            return jsonify({"valid": True, "type": flag_type})
    return jsonify({"valid": False})


@app.route("/api/lab/<lab_id>/source")
def lab_source(lab_id):
    """Return path to the lab's source zip for download."""
    import zipfile, io
    lab = LABS_BY_ID.get(lab_id)
    if not lab:
        abort(404)
    lab_dir = os.path.join(os.path.dirname(__file__), "..", "labs", lab_id)
    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(lab_dir):
            # Exclude build artefacts
            dirs[:] = [d for d in dirs if d not in ("node_modules", "__pycache__", ".git", "target")]
            for f in files:
                abs_path = os.path.join(root, f)
                rel_path = os.path.relpath(abs_path, lab_dir)
                zf.write(abs_path, rel_path)
    buf.seek(0)
    from flask import send_file
    return send_file(buf, mimetype="application/zip",
                     as_attachment=True, download_name=f"{lab['name']}-source.zip")


@app.route("/api/lab/<lab_id>/logs/stream")
def lab_logs_stream(lab_id):
    """
    SSE endpoint — streams Docker container logs (stdout + stderr) in real time.
    Query params:
      tail=200   — how many historical lines to send on connect (default 200)
    """
    if lab_id not in LABS_BY_ID:
        abort(404)

    tail = request.args.get("tail", "200")

    def generate():
        try:
            container = client.containers.get(_container_name(lab_id))
        except docker.errors.NotFound:
            yield f"data: [container not running]\n\n"
            return

        # Yield tail of historical logs first, then stream live
        try:
            for chunk in container.logs(
                stream=True,
                follow=True,
                stdout=True,
                stderr=True,
                tail=int(tail),
            ):
                line = chunk.decode("utf-8", errors="replace").rstrip("\n")
                for part in line.split("\n"):
                    yield f"data: {_redact(part)}\n\n"
        except docker.errors.NotFound:
            yield f"data: [container stopped]\n\n"
        except Exception as e:
            yield f"data: [error: {e}]\n\n"

    return Response(
        stream_with_context(generate()),
        mimetype="text/event-stream",
        headers={
            "Cache-Control": "no-cache",
            "X-Accel-Buffering": "no",
        },
    )


@app.route("/api/lab/<lab_id>/logs/snapshot")
def lab_logs_snapshot(lab_id):
    """Return last N lines of logs as JSON (non-streaming, for initial page load)."""
    if lab_id not in LABS_BY_ID:
        abort(404)
    tail = int(request.args.get("tail", "500"))
    try:
        container = client.containers.get(_container_name(lab_id))
        raw = container.logs(stdout=True, stderr=True, tail=tail)
        lines = raw.decode("utf-8", errors="replace").splitlines()
        return jsonify({"lines": lines})
    except docker.errors.NotFound:
        return jsonify({"lines": ["[container not running]"]})


def _db_container_name(lab_id: str) -> str:
    return f"arena-{lab_id}-db"


@app.route("/api/lab/<lab_id>/dblogs/available")
def lab_dblogs_available(lab_id):
    """Check whether a companion DB container exists for this lab."""
    if lab_id not in LABS_BY_ID:
        abort(404)
    try:
        c = client.containers.get(_db_container_name(lab_id))
        return jsonify({"available": True, "status": c.status})
    except docker.errors.NotFound:
        return jsonify({"available": False})


@app.route("/api/lab/<lab_id>/dblogs/stream")
def lab_dblogs_stream(lab_id):
    """SSE endpoint — streams DB companion container logs."""
    if lab_id not in LABS_BY_ID:
        abort(404)

    tail = request.args.get("tail", "200")

    def generate():
        try:
            container = client.containers.get(_db_container_name(lab_id))
        except docker.errors.NotFound:
            yield "data: [db container not found - start the lab first]\n\n"
            return

        try:
            for chunk in container.logs(
                stream=True,
                follow=True,
                stdout=True,
                stderr=True,
                tail=int(tail),
            ):
                line = chunk.decode("utf-8", errors="replace").rstrip("\n")
                for part in line.split("\n"):
                    yield f"data: {_redact(part)}\n\n"
        except docker.errors.NotFound:
            yield "data: [db container stopped]\n\n"
        except Exception as e:
            yield f"data: [error: {e}]\n\n"

    return Response(
        stream_with_context(generate()),
        mimetype="text/event-stream",
        headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"},
    )


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=8080, debug=True, threaded=True)
