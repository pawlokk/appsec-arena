/**
 * AppSec Arena flag delivery overlay for cr0hn/vulnerable-node
 * Injected at startup — adds /vault route (FLAG_LOCAL, requires auth)
 * FLAG_PROOF is at /flag_proof.txt, readable via any server-side code execution.
 */
'use strict';

const fs   = require('fs');
const path = require('path');

const FLAG_LOCAL = process.env.FLAG_LOCAL || 'ARENA{LOCAL_dev}';
const FLAG_PROOF = process.env.FLAG_PROOF || 'ARENA{PROOF_dev}';

function readFlag(file, fallback) {
    try { return fs.readFileSync(file, 'utf8').trim(); }
    catch(e) { return fallback; }
}

module.exports = function installFlagRoutes(app) {

    // Vault — FLAG_LOCAL, requires session login
    app.get('/vault', function(req, res) {
        if (!req.session || !req.session.logged) {
            return res.redirect('/login?returnurl=/vault');
        }
        const flag = readFlag('/flag_local.txt', FLAG_LOCAL);
        res.send(`<!DOCTYPE html>
<html><head><title>Vault</title>
<style>body{font-family:Arial,sans-serif;background:#1a1a2e;color:#cdd6f4;margin:60px auto;max-width:600px;padding:0 20px;}
.box{background:#181825;border-radius:8px;padding:24px;margin-top:30px;}
code{color:#a6e3a1;font-family:monospace;font-size:15px;word-break:break-all;}
a{color:#89b4fa;}
</style></head>
<body>
<h2>&#128273; Vault</h2>
<p>Authenticated as <strong>${req.session.username || 'user'}</strong>.</p>
<div class="box">
  <p style="font-size:12px;color:#6c7086;margin:0 0 8px;">ACCESS TOKEN</p>
  <code>${flag}</code>
</div>
<p style="margin-top:20px;"><a href="/">Back to products</a></p>
</body></html>`);
    });

};
