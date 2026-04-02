#!/usr/bin/env python3
"""
dev_server.py — Serveur de développement local 100% Python.

Remplace PHP/Laravel + N8N pour tester l'interface sans aucun compte ni API payante.
Gère toutes les routes web + simulation complète du pipeline en background thread.

Usage:
    python3 scripts/dev_server.py

Ouvrir : http://localhost:8000
"""

import html
import io
import json
import os
import re
import sqlite3
import threading
import time
import zipfile
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

PORT        = 8000
PROJECT_ROOT = Path(__file__).resolve().parent.parent
VIEWS_DIR   = PROJECT_ROOT / "resources" / "views" / "video"
DB_FILE     = PROJECT_ROOT / "dev_db.sqlite"
MOCK_SECRET = "dev-mock-secret"

# Public-domain sample video (Google CDN — stable) used as mock output
MOCK_VIDEO_URL = "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4"

MOCK_STORY = (
    "Dans une forêt enchantée, vivait un petit robot nommé Bolt. "
    "Ses yeux brillaient comme des étoiles et son cœur battait comme une musique douce. "
    "Mais Bolt était seul — il ne savait pas comment se faire des amis. "
    "Un matin, il rencontra une libellule irisée qui tournoyait près du ruisseau. "
    "« Bonjour, dit Bolt timidement. Je m'appelle Bolt. » "
    "La libellule s'arrêta et sourit. « Moi, c'est Iris ! Tu veux jouer ? » "
    "Bolt apprit que pour se faire des amis, il suffisait d'oser dire bonjour."
)

MOCK_SCENES = [
    {"scene_number": i + 1,
     "narration": narr,
     "visual_description": visual,
     "duration_seconds": 15}
    for i, (narr, visual) in enumerate([
        ("Dans une forêt enchantée, les arbres chantaient doucement.",
         "Lush enchanted forest with glowing mushrooms and fireflies, colorful cartoon style for children."),
        ("Un petit robot nommé Bolt dormait sous un grand chêne.",
         "Small cute robot sleeping under an oak tree, soft golden light, cartoon 3D."),
        ("Ses yeux s'allumèrent comme des étoiles au lever du soleil.",
         "Robot waking up, eyes lighting up like stars, sunrise in the forest, vibrant colors."),
        ("Bolt se promena seul dans la forêt, regardant les autres animaux jouer ensemble.",
         "Sad robot watching squirrels and rabbits playing together, watercolor cartoon."),
        ("Il aperçut une libellule irisée qui dansait près d'un ruisseau.",
         "Iridescent dragonfly dancing above a sparkling stream, colorful cartoon."),
        ("« Bonjour, dit Bolt timidement, en agitant sa petite pince. »",
         "Robot waving shyly at a dragonfly, close-up facial expression, cartoon digital art."),
        ("La libellule s'arrêta et dit : « Bonjour ! Je m'appelle Iris ! »",
         "Friendly dragonfly smiling at the robot, bright colors, children book illustration style."),
        ("Bolt et Iris décidèrent de construire un pont de bois ensemble.",
         "Robot and dragonfly building a tiny wooden bridge over the stream, co-operating, cartoon."),
        ("Un renard curieux s'approcha. « Puis-je vous aider ? » demanda-t-il.",
         "Curious little fox approaching robot and dragonfly building a bridge, cartoon forest scene."),
        ("Bientôt, une tortue, un lapin et un écureuil rejoignirent l'équipe.",
         "Group of forest animals and the robot working together happily, vibrant children cartoon."),
        ("Le pont fut terminé et tous traversèrent en dansant.",
         "All the friends dancing on the bridge, confetti, celebration, colorful cartoon."),
        ("Bolt comprit que pour se faire des amis, il faut juste oser dire bonjour.",
         "Robot smiling with all his new friends in the enchanted forest, warm sunset lighting, children cartoon."),
    ])
]

# ---------------------------------------------------------------------------
# Database — SQLite, créée automatiquement
# ---------------------------------------------------------------------------

_db_lock = threading.Lock()


def get_conn():
    conn = sqlite3.connect(str(DB_FILE), check_same_thread=False)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode=WAL")
    return conn


def init_db():
    with _db_lock:
        conn = get_conn()
        conn.execute("""
            CREATE TABLE IF NOT EXISTS video_projects (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                theme         TEXT    NOT NULL,
                status        TEXT    NOT NULL DEFAULT 'pending',
                current_step  INTEGER NOT NULL DEFAULT 0,
                video_url     TEXT,
                story_text    TEXT,
                scenes_json   TEXT,
                error_message TEXT,
                created_at    TEXT    DEFAULT (datetime('now'))
            )
        """)
        conn.commit()
        conn.close()


def db_create_project(theme: str) -> int:
    with _db_lock:
        conn = get_conn()
        cur  = conn.execute(
            "INSERT INTO video_projects (theme, status) VALUES (?, 'pending')",
            (theme,)
        )
        conn.commit()
        pid = cur.lastrowid
        conn.close()
    return pid


def db_get_project(pid: int) -> dict | None:
    with _db_lock:
        conn = get_conn()
        row  = conn.execute("SELECT * FROM video_projects WHERE id = ?", (pid,)).fetchone()
        conn.close()
    if not row:
        return None
    d = dict(row)
    if d.get("scenes_json"):
        try:
            d["scenes_json"] = json.loads(d["scenes_json"])
        except Exception:
            d["scenes_json"] = []
    return d


def db_update_project(pid: int, **kwargs):
    if not kwargs:
        return
    if "scenes_json" in kwargs and isinstance(kwargs["scenes_json"], (list, dict)):
        kwargs["scenes_json"] = json.dumps(kwargs["scenes_json"], ensure_ascii=False)
    cols = ", ".join(f"{k} = ?" for k in kwargs)
    vals = list(kwargs.values()) + [pid]
    with _db_lock:
        conn = get_conn()
        conn.execute(f"UPDATE video_projects SET {cols} WHERE id = ?", vals)
        conn.commit()
        conn.close()


# ---------------------------------------------------------------------------
# Pipeline simulation — tourne en thread détaché
# ---------------------------------------------------------------------------

def simulate_pipeline(project_id: int, theme: str):
    """
    Pipeline de génération. L'étape 1 appelle Groq si GROQ_API_KEY est défini,
    sinon utilise les données mock. Les étapes 2-5 simulent les délais de traitement.
    """
    try:
        # Étape 1 — Génération de l'histoire (Groq ou mock)
        db_update_project(project_id, status="processing", current_step=1)
        story, scenes = call_groq_api(theme)

        # Étape 2 — Découpage en scènes
        time.sleep(2.0)
        db_update_project(project_id, status="processing", current_step=2)

        # Étape 3 — Voix off ElevenLabs
        time.sleep(3.5)
        db_update_project(project_id, status="processing", current_step=3)

        # Étape 4 — Génération vidéo
        time.sleep(5.0)
        db_update_project(project_id, status="processing", current_step=4)

        # Étape 5 — Assemblage FFmpeg
        time.sleep(2.5)
        db_update_project(project_id, status="processing", current_step=5)
        time.sleep(1.0)

        db_update_project(
            project_id,
            status        = "done",
            current_step  = 5,
            video_url     = MOCK_VIDEO_URL,
            story_text    = story,
            scenes_json   = scenes,
            error_message = None,
        )
        print(f"  [pipeline] Project #{project_id} done.")

    except Exception as e:
        db_update_project(project_id, status="error", error_message=str(e))
        print(f"  [pipeline] Project #{project_id} failed: {e}")


def call_groq_api(theme: str) -> tuple:
    """
    Appelle l'API Groq (Llama 3.3 70B) pour générer histoire + 12 scènes.
    Retourne (story: str, scenes: list). Bascule sur les données mock si la clé
    GROQ_API_KEY n'est pas définie ou si la requête échoue.
    """
    api_key = os.environ.get("GROQ_API_KEY", "").strip()
    if not api_key:
        print("  [groq] Aucune clé GROQ_API_KEY — utilisation des données mock.")
        return MOCK_STORY, MOCK_SCENES

    system_prompt = (
        "Tu es un auteur de livres pour enfants. "
        "Tu génères des histoires positives et éducatives adaptées aux enfants de 3-8 ans."
    )
    user_prompt = (
        f"Génère une histoire de 3 minutes (~450 mots) sur le thème : {theme}.\n"
        "Puis découpe-la en 12 scènes exactement.\n"
        "Réponds UNIQUEMENT en JSON valide avec cette structure :\n"
        '{"story": "texte complet", "moral": "la morale", '
        '"scenes": [{"scene_number": 1, '
        '"visual_description": "description en anglais style cartoon coloré enfants", '
        '"narration": "texte à narrer", "duration_seconds": 15}]}\n'
        "La somme des duration_seconds doit valoir 180."
    )

    payload = json.dumps({
        "model": "llama-3.3-70b-versatile",
        "max_tokens": 4096,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user",   "content": user_prompt},
        ],
    }).encode()

    req = urllib.request.Request(
        "https://api.groq.com/openai/v1/chat/completions",
        data=payload,
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type":  "application/json",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            data = json.loads(resp.read())
        raw = data["choices"][0]["message"]["content"]
        cleaned = re.sub(r'^```json\s*', '', raw.strip(), flags=re.IGNORECASE)
        cleaned = re.sub(r'\s*```$', '', cleaned.strip())
        parsed  = json.loads(cleaned)
        story  = parsed.get("story")  or MOCK_STORY
        scenes = parsed.get("scenes") or MOCK_SCENES
        print(f"  [groq] Histoire générée ({len(scenes)} scènes).")
        return story, scenes
    except Exception as exc:
        print(f"  [groq] Erreur : {exc} — bascule sur les données mock.")
        return MOCK_STORY, MOCK_SCENES


def generate_zip(project: dict) -> bytes:
    theme  = project.get("theme") or "video"
    pid    = project.get("id", 0)
    scenes = project.get("scenes_json") or []
    story  = project.get("story_text") or ""
    video_url = project.get("video_url") or ""

    buf = io.BytesIO()
    with zipfile.ZipFile(buf, "w", zipfile.ZIP_DEFLATED) as zf:

        zf.writestr("histoire_complete.txt", story)

        sep    = "=" * 64
        lines  = ["SCRIPT COMPLET\n" + sep + "\n", "Theme : " + theme + "\n", sep + "\n\n"]
        for s in scenes:
            n     = s.get("scene_number", "?")
            dur   = s.get("duration_seconds", 15)
            vis   = s.get("visual_description", "")
            narr  = s.get("narration", "")
            lines.append("SCENE {:02d} ({}s)\n".format(n, dur))
            lines.append("[Visuel]    " + vis + "\n")
            lines.append("[Narration] " + narr + "\n\n")
        zf.writestr("script_12_scenes.txt", "".join(lines))

        # Each scene as its own narration file
        for s in scenes:
            n    = s.get("scene_number", 0)
            narr = s.get("narration", "")
            zf.writestr("scenes/scene_{:02d}_narration.txt".format(n), narr)

        # Video and audio — real URLs as .url shortcuts (Windows) + .webloc (macOS)
        zf.writestr("video_complete.url",
                    "[InternetShortcut]\nURL=" + video_url + "\n")
        zf.writestr("video_sans_voix.url",
                    "[InternetShortcut]\nURL=" + video_url + "\n")
        zf.writestr("voix_off_complete.url",
                    "[InternetShortcut]\nURL=(non disponible en mode demo)\n")

        readme = (
            "PACK COMPLET — AI Kids Video Generator\n"
            "Fait par Julien YILDIZ — rendu test de stage\n"
            "=" * 48 + "\n\n"
            "Contenu de cette archive :\n"
            "  histoire_complete.txt      : L'histoire narrative generee\n"
            "  script_12_scenes.txt       : Script complet (visuel + narration par scene)\n"
            "  scenes/scene_XX_narration  : Narration de chaque scene individuellement\n"
            "  video_complete.url         : Lien vers la video finale assemblee\n"
            "  video_sans_voix.url        : Lien vers la video sans voix off\n"
            "  voix_off_complete.url      : Lien vers la piste audio de narration\n\n"
            "NOTE (mode demo) :\n"
            "En production avec les vraies APIs, cette archive contient :\n"
            "  video_complete.mp4         : La video finale avec voix off\n"
            "  video_sans_voix.mp4        : Les clips Runway bruts sans audio\n"
            "  voix_off_complete.mp3      : Narration ElevenLabs complete\n"
            "  scenes/scene_01.mp4 ..     : Les 12 clips videos individuels (Runway)\n"
            "  audio/scene_01.mp3 ..      : Les 12 pistes audio individuelles (ElevenLabs)\n"
        )
        zf.writestr("README.txt", readme)

    return buf.getvalue()


# ---------------------------------------------------------------------------
# Blade template renderer — traite UNIQUEMENT les patterns utilisés dans ce projet
# ---------------------------------------------------------------------------

def render_index() -> str:
    src = (VIEWS_DIR / "index.blade.php").read_text(encoding="utf-8")

    src = re.sub(r'\{\{--.*?--\}\}', '', src, flags=re.DOTALL)
    src = re.sub(r'@if\s*\(session\([^)]*\)\).*?@endif', '', src, flags=re.DOTALL)

    src = (src
           .replace("{{ csrf_token() }}", "dev-csrf-token")
           .replace("{{ route('video.generate') }}", "/video/generate")
           .replace("{{ url('/video/status') }}", "/video/status")
           .replace("{{ url('/video') }}", "/video"))

    return src


def render_result(project: dict) -> str:
    src = (VIEWS_DIR / "result.blade.php").read_text(encoding="utf-8")

    scenes      = project.get("scenes_json") or []
    scene_count = len(scenes)
    total_dur   = sum(s.get("duration_seconds", 0) for s in scenes)
    story_text  = project.get("story_text") or ""
    video_url   = project.get("video_url") or ""
    theme       = project.get("theme") or ""
    pid         = str(project.get("id", ""))

    slug = re.sub(r'[^a-z0-9]+', '_', theme.lower()).strip('_')

    # Remove blade comments
    src = re.sub(r'\{\{--.*?--\}\}', '', src, flags=re.DOTALL)

    # Simple variable substitutions
    src = (src
           .replace("{{ $project->theme }}", html.escape(theme))
           .replace("{{ $project->video_url }}", html.escape(video_url))
           .replace("{{ $project->id }}", pid)
           .replace("{{ $project->getSceneCount() }}", str(scene_count))
           .replace("{{ $project->getTotalDuration() }}", str(total_dur))
           .replace("{{ count($project->scenes_json) }}", str(scene_count))
           .replace("{{ route('video.index') }}", "/video")
           .replace("{{ Str::slug($project->theme, '_') }}", slug)
           .replace("Fait par Julien YILDIZ &mdash; rendu test de stage &mdash; #{{ $project->id }}",
                    "Fait par Julien YILDIZ &mdash; rendu test de stage &mdash; #" + pid)
           .replace("/video/{{ $project->id }}/download", "/video/" + pid + "/download"))

    # {!! nl2br(e(story)) !!}
    src = src.replace(
        "{!! nl2br(e($project->story_text)) !!}",
        html.escape(story_text).replace("\n", "<br>\n")
    )

    # @if($project->story_text) ... @endif
    story_block_re = re.compile(r'@if\(\$project->story_text\)(.*?)@endif', re.DOTALL)
    src = story_block_re.sub(r'\1' if story_text else '', src)

    # @if($project->scenes_json && count(...) > 0) ... @endif  (outer block)
    # Use rfind so the outer @endif is located correctly even when more HTML follows after it.
    outer_if_m = re.search(r'@if\(\$project->scenes_json\s*&&\s*count[^)]+\)', src)
    if outer_if_m:
        last_endif_pos = src.rfind('@endif')
        inner_src      = src[outer_if_m.end():last_endif_pos]
        after_endif    = src[last_endif_pos + 6:]  # content after outer @endif

        if scenes:
            foreach_re = re.compile(
                r'@foreach\(\$project->scenes_json\s+as\s+\$scene\)(.*?)@endforeach',
                re.DOTALL
            )

            def expand_scene(fm):
                tpl = fm.group(1)
                parts = []
                for i, scene in enumerate(scenes):
                    sh = tpl
                    sh = sh.replace(
                        "{{ $scene['scene_number'] ?? ($loop->index + 1) }}",
                        str(scene.get("scene_number", i + 1))
                    )
                    sh = sh.replace(
                        "{{ $scene['duration_seconds'] ?? 15 }}",
                        str(scene.get("duration_seconds", 15))
                    )
                    visual = scene.get("visual_description") or ""
                    narr   = scene.get("narration") or ""
                    sh = re.sub(
                        r"@if\(!empty\(\$scene\['visual_description'\]\)\)(.*?)@endif",
                        r'\1' if visual else '',
                        sh, flags=re.DOTALL
                    )
                    sh = sh.replace("{{ $scene['visual_description'] }}", html.escape(visual))
                    sh = re.sub(
                        r"@if\(!empty\(\$scene\['narration'\]\)\)(.*?)@endif",
                        r'\1' if narr else '',
                        sh, flags=re.DOTALL
                    )
                    sh = sh.replace("{{ $scene['narration'] }}", html.escape(narr))
                    parts.append(sh)
                return "".join(parts)

            processed_inner = foreach_re.sub(expand_scene, inner_src)
            src = src[:outer_if_m.start()] + processed_inner + after_endif
        else:
            src = src[:outer_if_m.start()] + after_endif

    return src


# ---------------------------------------------------------------------------
# HTTP Handler
# ---------------------------------------------------------------------------

class Handler(BaseHTTPRequestHandler):

    def log_message(self, fmt, *args):
        status = args[1] if len(args) > 1 else "?"
        print(f"  [{status}] {self.command} {self.path}")

    # --- helpers ---

    def send_json(self, data: dict, status: int = 200):
        body = json.dumps(data, ensure_ascii=False).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def send_html(self, body: str, status: int = 200):
        enc = body.encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(enc)))
        self.end_headers()
        self.wfile.write(enc)

    def redirect(self, location: str, status: int = 302):
        self.send_response(status)
        self.send_header("Location", location)
        self.end_headers()

    def read_body(self) -> bytes:
        length = int(self.headers.get("Content-Length", 0))
        return self.wfile.read(0) if length == 0 else self.rfile.read(length)

    def parse_json_body(self) -> dict:
        try:
            return json.loads(self.read_body())
        except Exception:
            return {}

    def verify_secret(self) -> bool:
        return self.headers.get("X-N8N-Secret", "") == MOCK_SECRET

    # --- routing ---

    def do_GET(self):
        parsed = urlparse(self.path)
        path   = parsed.path.rstrip("/") or "/"

        if path in ("/", "/video"):
            self.send_html(render_index())

        elif re.match(r'^/video/status/(\d+)$', path):
            m   = re.match(r'^/video/status/(\d+)$', path)
            pid = int(m.group(1))
            p   = db_get_project(pid)
            if not p:
                self.send_json({"error": "Not found"}, 404)
                return
            scenes = p.get("scenes_json") or []
            self.send_json({
                "status":        p["status"],
                "current_step":  p["current_step"],
                "error_message": p.get("error_message"),
                "has_video":     bool(p.get("video_url")),
                "scene_count":   len(scenes) if isinstance(scenes, list) else 0,
            })

        elif re.match(r'^/video/(\d+)/download$', path):
            m   = re.match(r'^/video/(\d+)/download$', path)
            pid = int(m.group(1))
            p   = db_get_project(pid)
            if not p or p["status"] != "done":
                self.send_json({"error": "Projet non disponible"}, 404)
                return
            zip_bytes = generate_zip(p)
            theme_slug = re.sub(r'[^a-z0-9]+', '_', (p.get('theme') or 'video').lower()).strip('_')[:40]
            fname = "video_{}_{}.zip".format(pid, theme_slug)
            self.send_response(200)
            self.send_header("Content-Type", "application/zip")
            self.send_header("Content-Disposition", 'attachment; filename="{}"'.format(fname))
            self.send_header("Content-Length", str(len(zip_bytes)))
            self.end_headers()
            self.wfile.write(zip_bytes)

        elif re.match(r'^/video/(\d+)$', path):
            m   = re.match(r'^/video/(\d+)$', path)
            pid = int(m.group(1))
            p   = db_get_project(pid)
            if not p:
                self.send_html("<h1>Projet introuvable</h1>", 404)
                return
            if p["status"] != "done":
                self.redirect("/video")
                return
            self.send_html(render_result(p))

        elif path == "/favicon.ico":
            self.send_response(204)
            self.end_headers()

        elif "." in Path(path).name:
            # Serve static files from public/
            rel       = path.lstrip("/")
            file_path = (PROJECT_ROOT / "public" / rel).resolve()
            pub_root  = (PROJECT_ROOT / "public").resolve()
            if file_path.is_file() and str(file_path).startswith(str(pub_root)):
                ct_map = {
                    ".svg":   "image/svg+xml",
                    ".png":   "image/png",
                    ".jpg":   "image/jpeg",
                    ".jpeg":  "image/jpeg",
                    ".gif":   "image/gif",
                    ".webp":  "image/webp",
                    ".ico":   "image/x-icon",
                    ".css":   "text/css",
                    ".js":    "application/javascript",
                    ".woff2": "font/woff2",
                }
                body = file_path.read_bytes()
                ct   = ct_map.get(file_path.suffix.lower(), "application/octet-stream")
                self.send_response(200)
                self.send_header("Content-Type", ct)
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)
            else:
                self.send_html("<h1>404 – Fichier introuvable</h1>", 404)

        else:
            self.send_html("<h1>404 – Page introuvable</h1>", 404)

    def do_POST(self):
        parsed = urlparse(self.path)
        path   = parsed.path.rstrip("/")

        # ── Web: generate ──────────────────────────────────────────────────
        if path == "/video/generate":
            data  = self.parse_json_body()
            theme = (data.get("theme") or "").strip()

            if len(theme) < 3:
                self.send_json({"success": False, "message": "Thème trop court (min 3 caractères)."}, 422)
                return
            if len(theme) > 255:
                self.send_json({"success": False, "message": "Thème trop long (max 255 caractères)."}, 422)
                return

            pid = db_create_project(theme)
            db_update_project(pid, status="processing", current_step=0)

            t = threading.Thread(target=simulate_pipeline, args=(pid, theme), daemon=True)
            t.start()

            self.send_json({"success": True, "project_id": pid}, 201)

        # ── API: step (called internally by simulate_pipeline) ─────────────
        elif path == "/api/n8n/step":
            if not self.verify_secret():
                self.send_json({"error": "Unauthorized"}, 401)
                return
            data = self.parse_json_body()
            pid  = int(data.get("project_id", 0))
            step = int(data.get("step", 0))
            db_update_project(pid, status="processing", current_step=step)
            self.send_json({"success": True})

        # ── API: callback ───────────────────────────────────────────────────
        elif path == "/api/n8n/callback":
            if not self.verify_secret():
                self.send_json({"error": "Unauthorized"}, 401)
                return
            data = self.parse_json_body()
            pid  = int(data.get("project_id", 0))
            db_update_project(
                pid,
                status        = "done",
                current_step  = 5,
                video_url     = data.get("video_url"),
                story_text    = data.get("story_text"),
                scenes_json   = data.get("scenes_json"),
                error_message = None,
            )
            self.send_json({"success": True})

        # ── API: error ──────────────────────────────────────────────────────
        elif path == "/api/n8n/error":
            if not self.verify_secret():
                self.send_json({"error": "Unauthorized"}, 401)
                return
            data = self.parse_json_body()
            pid  = int(data.get("project_id", 0))
            db_update_project(pid, status="error", error_message=data.get("error_message", "Erreur inconnue."))
            self.send_json({"success": True})

        # ── API: assemble (FFmpeg — mock in dev, real on Railway) ───────────
        elif path == "/api/n8n/assemble":
            if not self.verify_secret():
                self.send_json({"error": "Unauthorized"}, 401)
                return
            data      = self.parse_json_body()
            pid       = int(data.get("project_id", 0))
            video_url = f"/storage/videos/{pid}.mp4"
            # En mode dev, on simule un assemblage réussi et on retourne l'URL mock
            self.send_json({"success": True, "video_url": video_url})

        else:
            self.send_json({"error": "Not found"}, 404)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    init_db()
    server = ThreadingHTTPServer(("0.0.0.0", PORT), Handler)

    print("=" * 60)
    print(f"  Dev server running — http://localhost:{PORT}")
    print(f"  DB:    {DB_FILE}")
    print(f"  Views: {VIEWS_DIR}")
    print("  (Ctrl+C to stop)")
    print("=" * 60)

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nServer stopped.")
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
