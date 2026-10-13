# lab-17-bmdyy-meme

A meme/message upload service originally created as a CTF challenge for 247CTF.com by bmdyy. Accepts XML POST data to create HTML message files stored server-side, and handles image (meme) file uploads with MIME type validation. Displays its own source code and XSD schema when accessed directly.

**Source:** https://github.com/bmdyy/meme_upload_service

## Vulnerabilities

- **XXE (XML External Entity) Injection** — `LIBXML_NOENT` and `LIBXML_DTDLOAD` flags are explicitly set when parsing user-supplied XML, enabling external entity expansion for arbitrary file read and SSRF.
- **RCE** via XXE-triggered SSRF or out-of-band exfiltration chain (CTF intended path).
- **Unrestricted File Upload / Insufficient MIME Validation** — relies on deprecated `mime_content_type()` and file extensions; a crafted PHP file disguised as an image could achieve RCE if the images directory permits script execution.
- **Information Disclosure** — detailed libxml error messages including file paths and line numbers are rendered directly to the page.
- **Stored XSS** — user-controlled XML field values are written into HTML files without output encoding.

## Stack

Vanilla PHP (no framework), native DOM/XML (libxml), Apache, Docker.

## Running

```bash
docker build -t lab-17-bmdyy-meme .
docker run -d -p 5117:80 lab-17-bmdyy-meme
```

Access at: http://localhost:5117
