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


def parse_args():
    parser = argparse.ArgumentParser(description="Assemble video scenes into a final MP4 via FFmpeg.")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--scenes",      help="JSON string of scenes array.")
    group.add_argument("--scenes-b64",  dest="scenes_b64", help="Base64-encoded JSON string of scenes.")
    group.add_argument("--scenes-file", dest="scenes_file", help="Path to a JSON file with the scenes array.")
    parser.add_argument("--output", required=True, help="Absolute output path for the final MP4.")
    return parser.parse_args()


def load_scenes(args) -> list:
    if args.scenes_b64:
        raw = base64.b64decode(args.scenes_b64).decode("utf-8")
    elif args.scenes_file:
        with open(args.scenes_file, "r", encoding="utf-8") as f:
            raw = f.read()
    else:
        raw = args.scenes

    scenes = json.loads(raw)
    if not isinstance(scenes, list) or len(scenes) == 0:
        raise ValueError("scenes must be a non-empty JSON array.")
    return scenes


def download_file(url: str, dest: str, label: str = "") -> None:
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            log.info(f"Download [{label}] attempt {attempt}/{MAX_RETRIES}: {url}")
            with requests.get(url, stream=True, timeout=DOWNLOAD_TIMEOUT) as r:
                r.raise_for_status()
                with open(dest, "wb") as f:
                    for chunk in r.iter_content(chunk_size=CHUNK_SIZE):
                        if chunk:
                            f.write(chunk)
            size_kb = os.path.getsize(dest) // 1024
            if size_kb == 0:
                raise ValueError(f"Downloaded file is empty: {dest}")
            log.info(f"  ✓ {label} ({size_kb} Ko)")
            return
        except Exception as e:
            log.warning(f"  Attempt {attempt} failed: {e}")
            if attempt < MAX_RETRIES:
                time.sleep(RETRY_DELAY)
    raise RuntimeError(f"Failed to download {label} after {MAX_RETRIES} attempts.")


def run_ffmpeg(args_list: list, step: str) -> None:
    cmd = ["ffmpeg", "-y"] + args_list
    log.info(f"[{step}] {' '.join(cmd)}")
    result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if result.returncode != 0:
        log.error(f"FFmpeg [{step}] stderr (last 1500 chars):\n{result.stderr[-1500:]}")
        raise RuntimeError(f"FFmpeg failed at step '{step}' (exit {result.returncode})")
    log.info(f"  ✓ step '{step}' done.")


def validate_mp4(path: str) -> None:
    result = subprocess.run(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration",
         "-of", "default=noprint_wrappers=1:nokey=1", path],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
    )
    if result.returncode != 0 or not result.stdout.strip():
        raise RuntimeError(f"Output file validation failed: {path}")
    duration = float(result.stdout.strip())
    if duration < 1.0:
        raise RuntimeError(f"Output video too short ({duration:.1f}s), assembly probably failed.")
    log.info(f"  ✓ Output validated: {duration:.1f}s")


def assemble(scenes: list, output_path: str) -> None:
    Path(output_path).parent.mkdir(parents=True, exist_ok=True)
    work_dir = tempfile.mkdtemp(prefix=f"video_asm_{os.getpid()}_")
    log.info(f"Work dir: {work_dir}")

    merged_clips = []

    try:
        for i, scene in enumerate(scenes):
            n = scene.get("scene_number", i + 1)
            video_url = scene.get("video_url")
            audio_src = scene.get("audio_url") or scene.get("audio_path")

            if not video_url:
                raise ValueError(f"Scene {n}: missing 'video_url'.")
            if not audio_src:
                raise ValueError(f"Scene {n}: missing 'audio_url' / 'audio_path'.")

            video_raw  = os.path.join(work_dir, f"v{n}.mp4")
            audio_raw  = os.path.join(work_dir, f"a{n}.mp3")
            merged_out = os.path.join(work_dir, f"merged_{n}.mp4")

            download_file(video_url, video_raw, label=f"video scene {n}")

            if audio_src.startswith(("http://", "https://")):
                download_file(audio_src, audio_raw, label=f"audio scene {n}")
            else:
                if not os.path.isfile(audio_src):
                    raise FileNotFoundError(f"Local audio not found: {audio_src}")
                shutil.copy2(audio_src, audio_raw)
                log.info(f"  ✓ audio scene {n} copied from local path")

            run_ffmpeg(
                ["-i", video_raw, "-i", audio_raw,
                 "-map", "0:v:0", "-map", "1:a:0",
                 "-c:v", "libx264", "-preset", "fast",
                 "-c:a", "aac", "-b:a", "128k",
                 "-shortest", "-movflags", "+faststart",
                 merged_out],
                step=f"merge scene {n}",
            )
            merged_clips.append(merged_out)
            log.info(f"Scene {n}/{len(scenes)} assembled.")

        if not merged_clips:
            raise RuntimeError("No clips to concatenate.")

        concat_file = os.path.join(work_dir, "concat.txt")
        with open(concat_file, "w", encoding="utf-8") as f:
            for clip in merged_clips:
                escaped = clip.replace("'", "\\'")
                f.write(f"file '{escaped}'\n")

        run_ffmpeg(
            ["-f", "concat", "-safe", "0", "-i", concat_file,
             "-c", "copy", "-movflags", "+faststart",
             output_path],
            step="final concat",
        )

        validate_mp4(output_path)
        size_mb = os.path.getsize(output_path) // (1024 * 1024)
        log.info(f"Done: {output_path} ({size_mb} Mo)")
        print(output_path)

    finally:
        shutil.rmtree(work_dir, ignore_errors=True)
        log.info(f"Cleaned up work dir: {work_dir}")


def main() -> int:
    args = parse_args()

    try:
        scenes = load_scenes(args)
    except Exception as e:
        log.error(f"Failed to load scenes: {e}")
        return 1

    log.info(f"Starting assembly: {len(scenes)} scenes → {args.output}")

    try:
        assemble(scenes, args.output)
        return 0
    except Exception as e:
        log.error(f"Assembly failed: {e}", exc_info=True)
        return 1


if __name__ == "__main__":
    sys.exit(main())
