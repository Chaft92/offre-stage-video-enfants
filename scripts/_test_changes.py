"""Quick test: verifies the ZIP endpoint and branding changes work correctly."""
import sys
sys.path.insert(0, str(__import__('pathlib').Path(__file__).parent))

from dev_server import generate_zip, render_index, render_result, MOCK_SCENES, MOCK_STORY, MOCK_VIDEO_URL
import zipfile, io

# Build a fake project
project = {
    "id": 99,
    "theme": "Un dragon qui vole",
    "status": "done",
    "current_step": 5,
    "story_text": MOCK_STORY,
    "scenes_json": MOCK_SCENES,
    "video_url": MOCK_VIDEO_URL,
    "error_message": None,
}

# --- Test ZIP generation ---
zip_bytes = generate_zip(project)
assert len(zip_bytes) > 500, "ZIP too small"
with zipfile.ZipFile(io.BytesIO(zip_bytes)) as zf:
    names = zf.namelist()
    assert "histoire_complete.txt" in names, "Missing histoire_complete.txt"
    assert "script_12_scenes.txt"  in names, "Missing script_12_scenes.txt"
    assert "video_complete.url"    in names, "Missing video_complete.url"
    assert "README.txt"            in names, "Missing README.txt"
    assert any("scene_" in n for n in names), "Missing scene files"
    story_in_zip = zf.read("histoire_complete.txt").decode("utf-8")
    assert "Bolt" in story_in_zip, "Story text missing from ZIP"
    script = zf.read("script_12_scenes.txt").decode("utf-8")
    assert "SCENE 01" in script, "Scene 1 missing from script"
    assert "SCENE 12" in script, "Scene 12 missing from script"
    url_content = zf.read("video_complete.url").decode()
    assert MOCK_VIDEO_URL in url_content, "Video URL missing from .url file"
print("ZIP OK — files:", names)

# --- Test branding in index page ---
idx = render_index()
assert "Julien YILDIZ" in idx, "Branding missing from index"
assert "Claude" not in idx or "AI Kids" in idx, "Old branding leak"
print("Index branding OK")

# --- Test branding + download link in result page ---
result = render_result(project)
assert "Julien YILDIZ" in result, "Branding missing from result"
assert "/video/99/download" in result, "Download link missing"
assert "pack complet" in result.lower(), "ZIP button text missing"
assert "Généré le" not in result, "Old footer still present"
assert "{{ " not in result, "Unrendered Blade tags in result page!"
print("Result page OK — download link and branding present")
print("\nAll tests passed.")
