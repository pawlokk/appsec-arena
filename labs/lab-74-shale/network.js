const IP_MAP = {
  '172.16.16.1': { 8000: 5000 },
  '172.16.16.2': { 8000: 5000, 8001: 8001 },
  '172.16.16.3': { 5432: 5432 },
  '172.16.16.4': { 8055: 8055 },
  '172.16.16.5': { 9000: 9000 },
  '172.16.16.6': { 6379: 6379 },
};

function resolveUrl(urlStr) {
  let parsed;
  try {
    parsed = new URL(urlStr);
  } catch (_) {
    return { url: urlStr, reachable: true };
  }

  const host = parsed.hostname;
  const port = parsed.port ? parseInt(parsed.port, 10) : (parsed.protocol === 'https:' ? 443 : 80);

  if (IP_MAP[host]) {
    const portMap = IP_MAP[host];
    if (portMap[port] !== undefined) {
      const mapped = portMap[port];
      const rewritten = urlStr.replace(host, '127.0.0.1').replace(`:${port}`, `:${mapped}`);
      return { url: rewritten, reachable: true };
    }
    return { reachable: false, code: 'ECONNREFUSED', address: host, port };
  }

  if (/^172\.16\./.test(host)) {
    return { reachable: false, code: 'EHOSTUNREACH', address: host, port };
  }

  return { url: urlStr, reachable: true };
}

module.exports = { resolveUrl };
