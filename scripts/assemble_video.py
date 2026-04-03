#!/usr/bin/env python3
import argparse
import base64
import json
import logging
import os
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path

import requests

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    stream=sys.stdout,
)
log = logging.getLogger("assemble_video")

DOWNLOAD_TIMEOUT = 120
CHUNK_SIZE       = 1024 * 1024  # 1 Mo
MAX_RETRIES      = 3
RETRY_DELAY      = 5


# ---------------------------------------------------------------------------
# Fonctions utilitaires
# ---------------------------------------------------------------------------

def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Assemble les clips vidéo et audios en une vidéo finale via FFmpeg."
    )
    parser.add_argument(
        "--scenes",
        required=True,
        help="JSON string contenant la liste des scènes (video_url + audio_path).",
    )
    parser.add_argument(
        "--output",
        required=True,
        help="Chemin absolu vers la vidéo MP4 de sortie (ex: /storage/videos/42.mp4).",
    )
    return parser.parse_args()


def download_file(url: str, dest_path: str, label: str = "") -> None:
    """Télécharge un fichier depuis une URL vers dest_path."""
    log.info(f"Téléchargement {label}: {url} → {dest_path}")
    with requests.get(url, stream=True, timeout=DOWNLOAD_TIMEOUT) as r:
        r.raise_for_status()
        with open(dest_path, "wb") as f:
            for chunk in r.iter_content(chunk_size=CHUNK_SIZE):
                if chunk:
                    f.write(chunk)
    log.info(f"  ✓ {label} téléchargé ({os.path.getsize(dest_path) // 1024} Ko)")


def run_ffmpeg(args: list, step: str) -> None:
    """Exécute FFmpeg avec les arguments donnés. Lève RuntimeError en cas d'échec."""
    cmd = ["ffmpeg", "-y"] + args
    log.info(f"FFmpeg [{step}]: {' '.join(cmd)}")
    result = subprocess.run(
        cmd,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )
    if result.returncode != 0:
        log.error(f"FFmpeg [{step}] stderr:\n{result.stderr[-2000:]}")
        raise RuntimeError(f"FFmpeg a échoué à l'étape '{step}' (code {result.returncode})")
    log.info(f"  ✓ '{step}' réussi.")


def ensure_output_dir(output_path: str) -> None:
    """Crée le dossier parent de la sortie s'il n'existe pas."""
    parent = Path(output_path).parent
    parent.mkdir(parents=True, exist_ok=True)


# ---------------------------------------------------------------------------
# Assemblage principal
# ---------------------------------------------------------------------------

def assemble(scenes: list, output_path: str) -> None:
    """
    1. Crée un dossier temporaire de travail.
    2. Pour chaque scène : télécharge clip vidéo + audio MP3.
    3. Mix vidéo + audio via FFmpeg → scene_merged_N.mp4
    4. Génère concat.txt.
    5. Concatène tous les clips → vidéo finale.
    6. Nettoie le dossier temporaire.
    """
    ensure_output_dir(output_path)
    work_dir = tempfile.mkdtemp(prefix="video_assembly_")
    log.info(f"Dossier de travail temporaire : {work_dir}")

    merged_clips = []

    try:
        for i, scene in enumerate(scenes):
            scene_num = scene.get("scene_number", i + 1)
            video_url = scene.get("video_url")
            audio_url = scene.get("audio_url")  # URL ou chemin local du MP3

            if not video_url:
                raise ValueError(f"Scène {scene_num} : 'video_url' manquant.")
            if not audio_url:
                raise ValueError(f"Scène {scene_num} : 'audio_url' manquant.")

            # ----------------------------------------------------------------
            # Téléchargement
            # ----------------------------------------------------------------
            video_raw  = os.path.join(work_dir, f"scene_{scene_num}_video.mp4")
            audio_raw  = os.path.join(work_dir, f"scene_{scene_num}_audio.mp3")
            merged_out = os.path.join(work_dir, f"scene_merged_{scene_num}.mp4")

            download_file(video_url, video_raw, label=f"vidéo scène {scene_num}")

            # L'audio peut être une URL HTTP ou un chemin local
            if audio_url.startswith(("http://", "https://")):
                download_file(audio_url, audio_raw, label=f"audio scène {scene_num}")
            else:
                # Chemin local => copie simple
                shutil.copy2(audio_url, audio_raw)
                log.info(f"  ✓ Audio scène {scene_num} copié depuis {audio_url}")

            # ----------------------------------------------------------------
            # Mix vidéo + audio (durée calée sur la vidéo)
            # ----------------------------------------------------------------
            run_ffmpeg(
                [
                    "-i", video_raw,
                    "-i", audio_raw,
                    "-map", "0:v:0",
                    "-map", "1:a:0",
                    "-c:v", "libx264",
                    "-c:a", "aac",
                    "-b:a", "128k",
                    "-shortest",       # coupe à la durée la plus courte des deux flux
                    "-movflags", "+faststart",
                    merged_out,
                ],
                step=f"merge scène {scene_num}",
            )
            merged_clips.append(merged_out)
            log.info(f"  → Scène {scene_num}/{len(scenes)} mergée.")

        if not merged_clips:
            raise RuntimeError("Aucun clip à assembler.")

        # --------------------------------------------------------------------
        # Création du fichier concat.txt pour FFmpeg concat demuxer
        # --------------------------------------------------------------------
        concat_file = os.path.join(work_dir, "concat.txt")
        with open(concat_file, "w", encoding="utf-8") as f:
            for clip in merged_clips:
                # Échappement des apostrophes pour la syntaxe FFmpeg
                safe_path = clip.replace("'", "'\\''")
                f.write(f"file '{safe_path}'\n")
        log.info(f"concat.txt généré ({len(merged_clips)} clips)")

        # --------------------------------------------------------------------
        # Concaténation finale
        # --------------------------------------------------------------------
        run_ffmpeg(
            [
                "-f", "concat",
                "-safe", "0",
                "-i", concat_file,
                "-c", "copy",
                "-movflags", "+faststart",
                output_path,
            ],
            step="concat final",
        )

        final_size = os.path.getsize(output_path) // (1024 * 1024)
        log.info(f"✅ Vidéo finale générée : {output_path} ({final_size} Mo)")
        print(output_path)  # N8N récupère ce chemin depuis stdout

    finally:
        # Nettoyage du dossier temporaire dans tous les cas
        try:
            shutil.rmtree(work_dir, ignore_errors=True)
            log.info(f"Dossier temporaire supprimé : {work_dir}")
        except Exception as cleanup_err:
            log.warning(f"Impossible de nettoyer {work_dir}: {cleanup_err}")


# ---------------------------------------------------------------------------
# Point d'entrée
# ---------------------------------------------------------------------------

def main() -> int:
    args = parse_args()

    # Parsing du JSON des scènes
    try:
        scenes = json.loads(args.scenes)
    except json.JSONDecodeError as e:
        log.error(f"JSON des scènes invalide : {e}")
        return 1

    if not isinstance(scenes, list) or len(scenes) == 0:
        log.error("Le JSON des scènes doit être un tableau non vide.")
        return 1

    log.info(f"Démarrage assemblage : {len(scenes)} scènes → {args.output}")

    try:
        assemble(scenes, args.output)
        return 0
    except Exception as e:
        log.error(f"Erreur d'assemblage : {e}", exc_info=True)
        return 1


if __name__ == "__main__":
    sys.exit(main())
