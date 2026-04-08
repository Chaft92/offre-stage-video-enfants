<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->theme }} - AI Kids Video</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://image.pollinations.ai">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0a0618; color: #e5e7eb; }

        .card-glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .cinema-container {
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            position: relative;
        }
        .cinema-screen {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #111;
            overflow: hidden;
        }
        .cinema-screen img,
        .cinema-screen video {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.25s ease-in-out;
        }
        .cinema-screen .hidden-media { opacity: 0; }
        .cinema-screen .active-media { opacity: 1; }

        @keyframes kb-zoom-in {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.12) translate(-1%, -0.5%); }
        }
        @keyframes kb-zoom-out {
            0% { transform: scale(1.12) translate(-1%, -0.5%); }
            100% { transform: scale(1) translate(0, 0); }
        }
        @keyframes kb-pan-right {
            0% { transform: scale(1.05) translate(-2%, 0); }
            100% { transform: scale(1.05) translate(2%, 0); }
        }
        @keyframes kb-pan-left {
            0% { transform: scale(1.05) translate(2%, 0); }
            100% { transform: scale(1.05) translate(-2%, 0); }
        }
        @keyframes kb-pan-up {
            0% { transform: scale(1.08) translate(0, 1%); }
            100% { transform: scale(1.08) translate(0, -1%); }
        }
        .kb-zoom-in    { animation: kb-zoom-in 12s ease-in-out forwards; }
        .kb-zoom-out   { animation: kb-zoom-out 12s ease-in-out forwards; }
        .kb-pan-right  { animation: kb-pan-right 12s ease-in-out forwards; }
        .kb-pan-left   { animation: kb-pan-left 12s ease-in-out forwards; }
        .kb-pan-up     { animation: kb-pan-up 12s ease-in-out forwards; }

        .cinema-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.85));
            padding: 2rem 1.5rem 1.5rem;
            z-index: 5;
        }

        .subtitle-bar {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            max-width: 90%;
            text-align: center;
            pointer-events: none;
            z-index: 10;
            transition: opacity 0.3s ease;
        }
        .subtitle-bar.hidden-sub { opacity: 0; }
        .subtitle-text {
            display: inline-block;
            background: rgba(0, 0, 0, 0.42);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 400;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            line-height: 1.4;
            text-shadow: none;
        }

        .cinema-controls {
            background: rgba(20, 10, 40, 0.95);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .progress-bar {
            flex: 1;
            height: 6px;
            background: rgba(255,255,255,0.15);
            border-radius: 3px;
            cursor: pointer;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #a855f7);
            border-radius: 3px;
            transition: width 0.3s linear;
        }
        .ctrl-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background 0.2s;
        }
        .ctrl-btn:hover { background: rgba(255,255,255,0.2); }
        .ctrl-btn.active { background: rgba(167, 139, 250, 0.4); }

        .voice-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(167, 139, 250, 0.2);
            border: 1px solid rgba(167, 139, 250, 0.3);
            color: #c4b5fd;
        }
        .scene-badge-part {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 2px 8px;
            border-radius: 4px;
        }
        .part-introduction { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
        .part-development { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
        .part-conclusion { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }

        .scene-thumb {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border-radius: 6px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: border-color 0.2s, transform 0.2s;
            background: #1a1a2e;
        }
        .scene-thumb:hover { transform: scale(1.05); }
        .scene-thumb.active-thumb { border-color: #a855f7; }

        .story-block {
            background: rgba(255,255,255,0.03);
            border-left: 3px solid #7c3aed;
            line-height: 1.85;
        }
        .scene-list-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            transition: border-color 0.2s;
        }
        .scene-list-item:hover, .scene-list-item.active-scene {
            border-color: rgba(167, 139, 250, 0.4);
        }

        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fade-in-up 0.5s ease forwards; }

        @keyframes spin-smooth { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        .preload-overlay {
            position: absolute;
            inset: 0;
            background: rgba(10, 6, 24, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 20;
            transition: opacity 0.6s ease;
        }
        .preload-overlay.done { opacity: 0; pointer-events: none; }
        .preload-bar {
            width: 200px;
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 1rem;
        }
        .preload-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #a855f7);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #4c1d95; border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen py-8 px-4">

    <div class="max-w-5xl mx-auto">

        <div class="text-center mb-6 fade-in-up">
            <h1 class="text-3xl font-extrabold text-white mb-2">{{ $project->theme }}</h1>
            <p class="text-gray-400 text-sm">
                {{ $project->getSceneCount() }} scenes &middot; {{ $project->getTotalDuration() }}s &middot; Projet #{{ $project->id }}
            </p>
        </div>

        @php
            $scenes = $project->scenes_json ?? [];
            $voiceLabels = [
                'narratrice' => 'Narratrice',
                'narrateur' => 'Narrateur',
                'enfant_fille' => 'Enfant (fille)',
                'enfant_garcon' => 'Enfant (garcon)',
            ];
        @endphp

        @if(count($scenes) > 0)
        <div class="cinema-container mb-6 fade-in-up" style="animation-delay:0.1s; position:relative;">

            <div class="preload-overlay" id="preload-overlay">
                <svg class="w-10 h-10 text-purple-400 mb-3" style="animation: spin-smooth 1.2s linear infinite;" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <p class="text-white font-semibold text-sm" id="preload-label">Chargement des illustrations...</p>
                <p class="text-gray-400 text-xs mt-1" id="preload-count">0 / {{ count($scenes) }}</p>
                <div class="preload-bar">
                    <div class="preload-fill" id="preload-fill" style="width:0%"></div>
                </div>
            </div>

            <div class="cinema-screen" id="cinema-screen">
                <div id="cinema-placeholder" class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-purple-900/50 to-indigo-900/50">
                    <div class="text-center">
                        <span class="text-6xl mb-4 block">&#127916;</span>
                        <p class="text-white text-lg font-semibold">Cliquez sur Play pour lancer le film</p>
                        <p class="text-gray-400 text-sm mt-2">{{ count($scenes) }} scenes illustrees avec narration IA</p>
                    </div>
                </div>

                <div class="subtitle-bar" id="subtitle-bar">
                    <span class="subtitle-text" id="subtitle-text"></span>
                </div>

                <div class="cinema-overlay" id="cinema-overlay" style="display:none">
                    <div class="flex items-center gap-3">
                        <span id="cinema-scene-label" class="scene-badge-part part-introduction">Scene 1</span>
                        <span id="cinema-voice-badge" class="voice-badge"></span>
                    </div>
                </div>
            </div>

            <div class="cinema-controls">
                <button class="ctrl-btn" id="btn-play" onclick="togglePlay()" title="Lecture / Pause">
                    <span id="play-icon">&#9654;</span>
                </button>
                <div class="progress-bar" id="progress-bar" onclick="seekProgress(event)">
                    <div class="progress-fill" id="progress-fill" style="width:0%"></div>
                </div>
                <span class="text-gray-400 text-xs font-mono whitespace-nowrap" id="scene-counter">0 / {{ count($scenes) }}</span>
                <button class="ctrl-btn" id="btn-sub" onclick="toggleSubtitles()" title="Sous-titres">CC</button>
                <button class="ctrl-btn" id="btn-restart" onclick="restartFilm()" title="Recommencer">&#8634;</button>
            </div>

            <div class="flex gap-2 p-3 overflow-x-auto" id="thumb-strip">
                @foreach($scenes as $i => $scene)
                <div class="flex-shrink-0" style="width:80px;height:45px;background:#1a1a2e;border-radius:6px;border:2px solid transparent;cursor:pointer;overflow:hidden;" id="thumb-wrap-{{ $i }}" onclick="jumpToScene({{ $i }})">
                    <img id="thumb-{{ $i }}"
                         alt="Scene {{ $scene['scene_number'] ?? $i+1 }}"
                         class="scene-thumb"
                         style="width:100%;height:100%;opacity:0;transition:opacity 0.3s;">
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-4 mb-8 fade-in-up" style="animation-delay:0.15s">
            <a href="{{ route('video.index') }}"
               class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                + Generer un nouveau film
            </a>
            <a href="/video/{{ $project->id }}/download"
               class="flex-1 py-3 rounded-xl text-sm font-semibold flex items-center justify-center gap-2
                      bg-white/5 border border-white/15 text-gray-300 hover:bg-white/10 hover:text-white transition-all duration-200">
                Telecharger le pack complet (.zip)
            </a>
        </div>

        @if($project->story_text)
        <div class="card-glass rounded-2xl p-6 mb-8 fade-in-up" style="animation-delay:0.2s">
            <h2 class="text-white text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="text-purple-400">&#128214;</span> L'histoire complete
            </h2>
            <div class="story-block rounded-r-xl p-4 text-gray-300 text-sm">
                {!! nl2br(e($project->story_text)) !!}
            </div>
            @if($project->moral)
            <div class="mt-4 p-4 rounded-xl bg-gradient-to-r from-amber-900/20 to-yellow-900/20 border border-amber-500/20">
                <p class="text-amber-300 text-sm font-semibold flex items-center gap-2 mb-1">
                    <span>&#128161;</span> La morale de l'histoire
                </p>
                <p class="text-amber-100/90 text-sm italic leading-relaxed">{{ $project->moral }}</p>
            </div>
            @endif
        </div>
        @endif

        @if(count($scenes) > 0)
        <div class="fade-in-up" style="animation-delay:0.3s">
            <h2 class="text-white text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="text-purple-400">&#127916;</span> Les {{ count($scenes) }} scenes
            </h2>
            <div class="flex flex-col gap-4">
                @foreach($scenes as $i => $scene)
                @php
                    $sceneNum = $scene['scene_number'] ?? ($i + 1);
                    $part = $scene['part'] ?? 'development';
                    $partClass = match($part) { 'introduction' => 'part-introduction', 'conclusion' => 'part-conclusion', default => 'part-development' };
                    $partLabel = match($part) { 'introduction' => 'Introduction', 'conclusion' => 'Conclusion', default => 'Developpement' };
                    $voiceType = $scene['voice'] ?? 'narratrice';
                    $voiceLabel = $voiceLabels[$voiceType] ?? 'Narratrice';
                @endphp
                <div class="scene-list-item p-4 cursor-pointer" id="scene-card-{{ $i }}" onclick="jumpToScene({{ $i }})">
                    <div class="flex gap-4">
                        <div class="w-32 h-20 rounded-lg flex-shrink-0 cursor-pointer bg-gray-800 overflow-hidden" onclick="jumpToScene({{ $i }})">
                            <img id="scene-img-{{ $i }}" alt="Scene {{ $sceneNum }}" class="w-full h-full object-cover" style="opacity:0;transition:opacity 0.3s;">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="text-white text-sm font-bold">Scene {{ $sceneNum }}</span>
                                <span class="scene-badge-part {{ $partClass }}">{{ $partLabel }}</span>
                                <span class="voice-badge">{{ $voiceLabel }}</span>
                                @if(!empty($scene['video_url']))
                                <span class="scene-badge-part part-development">Video</span>
                                @else
                                <span class="scene-badge-part part-conclusion">Image</span>
                                @endif
                                <span class="text-gray-500 text-xs ml-auto">{{ $scene['duration_seconds'] ?? 10 }}s</span>
                            </div>
                            <p class="text-gray-200 text-sm mb-1">{{ $scene['narration'] ?? '' }}</p>
                            <p class="text-gray-500 text-xs italic">{{ $scene['visual_description'] ?? '' }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="mt-8 text-center text-gray-500 text-xs pb-4">
            Fait par Julien YILDIZ &mdash; AI Kids Video Generator
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        var SCENES = @json($scenes);
        var PROJECT_ID = {{ $project->id }};
        var KB_EFFECTS = ['kb-zoom-in', 'kb-zoom-out', 'kb-pan-right', 'kb-pan-left', 'kb-pan-up'];

        var currentScene = -1;
        var isPlaying = false;
        var sceneTimer = null;
        var fallbackTimer = null;
        var subtitlesEnabled = true;

        var preloadedImages = {};
        var preloadedAudio = {};
        var preloadedVideos = {}; // pre-buffered video elements
        var allMediaReady = false;

        var totalItems = SCENES.length * 2; // images + audio
        var loadedItems = 0;
        var audioRetryMax = 10;
        var audioRetryDelay = 2500;

        /* ===== PRELOAD UI ===== */

        function updatePreloadUI() {
            var pct = totalItems > 0 ? Math.round((loadedItems / totalItems) * 100) : 100;
            var fill = document.getElementById('preload-fill');
            var count = document.getElementById('preload-count');
            var label = document.getElementById('preload-label');
            if (fill) fill.style.width = pct + '%';
            if (count) count.textContent = loadedItems + ' / ' + totalItems;

            if (loadedItems >= totalItems && !allMediaReady) {
                allMediaReady = true;
                if (label) label.textContent = 'Film pret !';
                // Start pre-buffering videos for first 3 scenes
                for (var v = 0; v < Math.min(3, SCENES.length); v++) prebufferVideo(v);
                setTimeout(function() {
                    var overlay = document.getElementById('preload-overlay');
                    if (overlay) overlay.classList.add('done');
                    autoStartFilm();
                }, 400);
            } else if (label && !allMediaReady) {
                var imgDone = Object.keys(preloadedImages).length;
                var audioDone = Object.keys(preloadedAudio).length;
                label.textContent = 'Preparation du film... Images ' + imgDone + '/' + SCENES.length + ' · Audio ' + audioDone + '/' + SCENES.length;
            }
        }

        function applyToDOM(idx, src) {
            var thumb = document.getElementById('thumb-' + idx);
            if (thumb) { thumb.src = src; thumb.style.opacity = '1'; }
            var sceneImg = document.getElementById('scene-img-' + idx);
            if (sceneImg) { sceneImg.src = src; sceneImg.style.opacity = '1'; }
        }

        function getFallbackImageUrl(scene, idx) {
            if (scene && scene.fallback_image_url) return scene.fallback_image_url;
            return 'https://picsum.photos/seed/akv-' + PROJECT_ID + '-' + (idx + 1) + '/1280/720';
        }

        /* ===== IMAGE PRELOAD ===== */

        (function preloadImages() {
            var queue = [];
            for (var i = 0; i < SCENES.length; i++) queue.push(i);
            var running = 0;
            var concurrency = 3;

            function next() {
                if (queue.length === 0) return;
                var idx = queue.shift();
                running++;
                var scene = SCENES[idx];
                if (!scene || !scene.image_url) { finish(idx, false); return; }

                var img = new Image();
                img.onload = function() { finish(idx, true, img); };
                img.onerror = function() {
                    var fb = getFallbackImageUrl(scene, idx);
                    if (fb && img.src !== fb) {
                        img.onload = function() { finish(idx, true, img); };
                        img.onerror = function() { finish(idx, false); };
                        img.src = fb;
                        return;
                    }
                    finish(idx, false);
                };
                img.src = scene.image_url;
            }

            function finish(idx, ok, img) {
                if (ok && img) { preloadedImages[idx] = img; applyToDOM(idx, img.src); }
                loadedItems++;
                running--;
                updatePreloadUI();
                next();
            }

            for (var c = 0; c < Math.min(concurrency, SCENES.length); c++) next();
        })();

        /* ===== AUDIO PRELOAD (with retries) ===== */

        (function preloadAllAudio() {
            for (var i = 0; i < SCENES.length; i++) {
                preloadOneAudio(i, 0);
            }
        })();

        function preloadOneAudio(idx, attempt) {
            var scene = SCENES[idx];
            if (!scene || !scene.narration) {
                loadedItems++;
                updatePreloadUI();
                return;
            }

            var sceneNum = scene.scene_number || (idx + 1);
            var url = '/video/' + PROJECT_ID + '/tts/' + sceneNum;

            fetch(url)
                .then(function(resp) {
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    return resp.blob();
                })
                .then(function(blob) {
                    if (blob.size < 500) throw new Error('Audio too small');
                    var blobUrl = URL.createObjectURL(blob);
                    var audio = new Audio(blobUrl);
                    audio.preload = 'auto';
                    preloadedAudio[idx] = audio;
                    loadedItems++;
                    updatePreloadUI();
                })
                .catch(function(err) {
                    if (attempt < audioRetryMax) {
                        setTimeout(function() { preloadOneAudio(idx, attempt + 1); }, audioRetryDelay);
                    } else {
                        loadedItems++;
                        updatePreloadUI();
                    }
                });
        }

        /* ===== VIDEO PRE-BUFFER ===== */

        function prebufferVideo(idx) {
            var scene = SCENES[idx];
            if (!scene) return;
            var url = (scene.video_url || '').trim();
            if (!url || preloadedVideos[idx]) return;

            var video = document.createElement('video');
            video.src = url;
            video.preload = 'auto';
            video.muted = true;
            video.playsInline = true;
            video.loop = true;
            video.setAttribute('playsinline', 'playsinline');
            video.style.zIndex = '3';

            video.onloadeddata = function() {
                preloadedVideos[idx] = video;
            };
            video.onerror = function() {
                // video failed, will use image only
            };
            // trigger download
            video.load();
        }

        /* ===== CINEMA DISPLAY ===== */

        function getKBEffect(index) {
            return KB_EFFECTS[index % KB_EFFECTS.length];
        }

        function showScene(index) {
            if (index < 0 || index >= SCENES.length) return;

            var screen = document.getElementById('cinema-screen');
            var placeholder = document.getElementById('cinema-placeholder');
            var overlay = document.getElementById('cinema-overlay');
            var scene = SCENES[index];

            if (placeholder) placeholder.style.display = 'none';
            overlay.style.display = '';

            // Fade out old media
            screen.querySelectorAll('.active-media').forEach(function(el) {
                el.classList.remove('active-media');
                KB_EFFECTS.forEach(function(k) { el.classList.remove(k); });
                el.classList.add('hidden-media');
                if (el.tagName === 'VIDEO') { try { el.pause(); } catch(e) {} }
                setTimeout(function() { el.remove(); }, 300);
            });

            var sceneVideoUrl = (scene.video_url || '').trim();

            if (sceneVideoUrl !== '') {
                // Check if we have a pre-buffered video ready
                var buffered = preloadedVideos[index];
                if (buffered && buffered.readyState >= 2) {
                    // Use pre-buffered video directly — no image poster needed
                    var v = buffered;
                    v.className = 'hidden-media';
                    v.style.zIndex = '3';
                    v.currentTime = 0;
                    screen.insertBefore(v, overlay);
                    v.offsetHeight;
                    v.classList.remove('hidden-media');
                    v.classList.add('active-media');
                    v.play().catch(function() {});
                    // Remove from cache so it can be re-created later
                    delete preloadedVideos[index];
                } else {
                    // Show image as brief cover, then crossfade to video
                    showImageCover(screen, index, scene, overlay);

                    var video = document.createElement('video');
                    video.src = sceneVideoUrl;
                    video.className = 'hidden-media';
                    video.preload = 'auto';
                    video.muted = true;
                    video.playsInline = true;
                    video.loop = true;
                    video.setAttribute('playsinline', 'playsinline');
                    video.style.zIndex = '3';

                    var videoFailed = false;
                    function fallbackToImage() {
                        if (videoFailed) return;
                        videoFailed = true;
                        try { video.pause(); } catch(e) {}
                        video.remove();
                    }

                    video.onerror = fallbackToImage;
                    var loadTimeout = setTimeout(fallbackToImage, 30000);

                    video.onloadeddata = function() {
                        clearTimeout(loadTimeout);
                        // Brief image cover: replace with video after 200ms
                        setTimeout(function() {
                            if (videoFailed) return;
                            screen.querySelectorAll('img.active-media').forEach(function(img) {
                                img.classList.remove('active-media');
                                KB_EFFECTS.forEach(function(k) { img.classList.remove(k); });
                                img.classList.add('hidden-media');
                                setTimeout(function() { img.remove(); }, 300);
                            });
                            video.classList.remove('hidden-media');
                            video.classList.add('active-media');
                            video.play().catch(function() { fallbackToImage(); });
                        }, 200);
                    };

                    screen.insertBefore(video, overlay);
                }
            } else {
                // No video — image with Ken Burns
                showImageCover(screen, index, scene, overlay);
            }

            // Pre-buffer next 2 scenes' videos
            prebufferVideo(index + 1);
            prebufferVideo(index + 2);

            // Subtitles
            var subtitleText = document.getElementById('subtitle-text');
            var subtitleBar = document.getElementById('subtitle-bar');
            subtitleText.textContent = scene.narration || '';
            if (subtitlesEnabled) subtitleBar.classList.remove('hidden-sub');

            // Scene indicators (subtle)
            var label = document.getElementById('cinema-scene-label');
            var part = scene.part || 'development';
            label.textContent = 'Scene ' + (scene.scene_number || index + 1);
            label.className = 'scene-badge-part part-' + part;
            document.getElementById('cinema-voice-badge').textContent = 'Narratrice';
            document.getElementById('scene-counter').textContent = (index + 1) + ' / ' + SCENES.length;
            document.getElementById('progress-fill').style.width = (((index + 1) / SCENES.length) * 100) + '%';

            // Highlight thumbnail
            document.querySelectorAll('[id^="thumb-wrap-"]').forEach(function(t) { t.style.borderColor = 'transparent'; });
            var tw = document.getElementById('thumb-wrap-' + index);
            if (tw) { tw.style.borderColor = '#a855f7'; tw.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' }); }

            // Highlight scene card
            document.querySelectorAll('.scene-list-item').forEach(function(c) { c.classList.remove('active-scene'); });
            var card = document.getElementById('scene-card-' + index);
            if (card) card.classList.add('active-scene');

            currentScene = index;
        }

        function showImageCover(screen, index, scene, overlay) {
            var cached = preloadedImages[index];
            var image;
            if (cached) {
                image = cached.cloneNode();
            } else {
                image = document.createElement('img');
                image.alt = 'Scene ' + (scene.scene_number || (index + 1));
                image.onerror = function() {
                    var fb = getFallbackImageUrl(scene, index);
                    if (image.src !== fb) image.src = fb;
                };
                image.src = scene.image_url || getFallbackImageUrl(scene, index);
            }
            image.className = 'hidden-media';
            screen.insertBefore(image, overlay);
            image.offsetHeight;
            image.classList.remove('hidden-media');
            image.classList.add('active-media');
            image.classList.add(getKBEffect(index));
        }

        /* ===== AUDIO PLAYBACK ===== */

        function playSceneAudio(index, onEnd) {
            var scene = SCENES[index];
            if (!scene || !scene.narration) { onEnd(); return; }

            var audio = preloadedAudio[index];
            if (audio) {
                audio.currentTime = 0;
                function done() { audio.removeEventListener('ended', done); audio.removeEventListener('error', done); onEnd(); }
                audio.addEventListener('ended', done);
                audio.addEventListener('error', done);
                audio.play().catch(function() { done(); });
            } else {
                onEnd();
            }
        }

        function stopAllAudio() {
            Object.keys(preloadedAudio).forEach(function(k) {
                try { preloadedAudio[k].pause(); preloadedAudio[k].currentTime = 0; } catch(e) {}
            });
            if (sceneTimer) { clearTimeout(sceneTimer); sceneTimer = null; }
            if (fallbackTimer) { clearTimeout(fallbackTimer); fallbackTimer = null; }
        }

        /* ===== CONTINUOUS FILM PLAYBACK ===== */

        function playScene(index) {
            if (index >= SCENES.length) { stopPlayback(); return; }

            showScene(index);

            var scene = SCENES[index];
            // Scene advances as soon as audio finishes (min 3s for very short narrations)
            var audioDone = false;
            var minTimeDone = false;
            var minDur = 3000;

            function advance() {
                if (audioDone && minTimeDone && isPlaying && currentScene === index) {
                    playScene(index + 1);
                }
            }

            playSceneAudio(index, function() {
                if (!isPlaying) return;
                audioDone = true;
                advance();
            });

            sceneTimer = setTimeout(function() {
                minTimeDone = true;
                advance();
            }, minDur);

            // Safety: if audio never fires `ended`, force advance after scene duration + 8s
            var maxDur = Math.max(5, (scene.duration_seconds || 12)) * 1000;
            fallbackTimer = setTimeout(function() {
                if (isPlaying && currentScene === index) {
                    stopAllAudio();
                    playScene(index + 1);
                }
            }, maxDur + 8000);
        }

        function autoStartFilm() {
            if (isPlaying) return;
            isPlaying = true;
            document.getElementById('play-icon').innerHTML = '&#9646;&#9646;';
            document.getElementById('btn-play').classList.add('active');
            playScene(0);
        }

        window.togglePlay = function() {
            if (isPlaying) {
                stopPlayback();
            } else {
                isPlaying = true;
                document.getElementById('play-icon').innerHTML = '&#9646;&#9646;';
                document.getElementById('btn-play').classList.add('active');
                if (currentScene < 0 || currentScene >= SCENES.length - 1) playScene(0);
                else playScene(currentScene);
            }
        };

        function stopPlayback() {
            isPlaying = false;
            stopAllAudio();
            document.getElementById('play-icon').innerHTML = '&#9654;';
            document.getElementById('btn-play').classList.remove('active');
        }

        window.restartFilm = function() {
            stopPlayback();
            currentScene = -1;
            isPlaying = true;
            document.getElementById('play-icon').innerHTML = '&#9646;&#9646;';
            document.getElementById('btn-play').classList.add('active');
            playScene(0);
        };

        window.jumpToScene = function(index) {
            stopAllAudio();
            showScene(index);
            if (isPlaying) playScene(index);
        };

        window.seekProgress = function(e) {
            var bar = document.getElementById('progress-bar');
            var rect = bar.getBoundingClientRect();
            var pct = (e.clientX - rect.left) / rect.width;
            var target = Math.floor(pct * SCENES.length);
            jumpToScene(Math.max(0, Math.min(target, SCENES.length - 1)));
        };

        window.toggleSubtitles = function() {
            subtitlesEnabled = !subtitlesEnabled;
            var bar = document.getElementById('subtitle-bar');
            var btn = document.getElementById('btn-sub');
            if (subtitlesEnabled) { bar.classList.remove('hidden-sub'); btn.classList.add('active'); }
            else { bar.classList.add('hidden-sub'); btn.classList.remove('active'); }
        };

        document.getElementById('btn-sub').classList.add('active');
    })();
    </script>
</body>
</html>