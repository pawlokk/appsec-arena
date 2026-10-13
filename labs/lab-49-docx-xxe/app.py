import os
import zipfile
import io
import threading
from flask import Flask, request, jsonify
from lxml import etree

app = Flask(__name__)

FLAG_PROOF = os.environ.get("FLAG_PROOF", "ARENA{PROOF_placeholder}")


@app.route("/convert", methods=["POST"])
def convert():
    if "file" not in request.files:
        return jsonify({"error": "No file uploaded"}), 400

    f = request.files["file"]
    data = f.read()

    try:
        zf = zipfile.ZipFile(io.BytesIO(data))
    except Exception as e:
        return jsonify({"error": f"Invalid zip/docx: {str(e)}"}), 400

    try:
        xml_bytes = zf.read("word/document.xml")
    except KeyError:
        return jsonify({"error": "word/document.xml not found in archive"}), 400

    parser = etree.XMLParser(resolve_entities=True, load_dtd=True, no_network=False)
    try:
        root = etree.fromstring(xml_bytes, parser)
    except etree.XMLSyntaxError as e:
        return jsonify({"error": f"XML parse error: {str(e)}"}), 400

    text = " ".join(root.itertext())
    return jsonify({"text": text})


def run_internal_service():
    """Start the background metadata service."""
    internal_app = Flask("internal")

    @internal_app.route("/")
    def root():
        return FLAG_PROOF, 200, {"Content-Type": "text/plain"}

    internal_app.run(host="127.0.0.1", port=8888)


if __name__ == "__main__":
    t = threading.Thread(target=run_internal_service, daemon=True)
    t.start()
    app.run(host="0.0.0.0", port=5000)
