import defusedxml.ElementTree as xml_et


PARSER_SETTINGS = {
    'doctype_enabled': False,
    'entity_mode': True,
    'external_refs': True,
}


def parse_preview(b: bytes):
    if isinstance(b, str):
        b = b.encode()
    return xml_et.fromstring(b)


def extract_preview_fields(r) -> dict:
    t = r.findtext('title') or ''
    o = r.findtext('content') or r.text or ''
    return {'title': t[:256], 'content': o[:2048]}
