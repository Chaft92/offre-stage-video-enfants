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
        .cinema-screen img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.8s ease-in-out;
        }
        .cinema-screen img.hidden-img { opacity: 0; }
        .cinema-screen img.active-img { opacity: 1; }

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
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            max-width: 80%;
            text-align: center;
            pointer-events: none;
            z-index: 10;
            transition: opacity 0.3s ease;
        }
        .subtitle-bar.hidden-sub { opacity: 0; }
        .subtitle-text {
            display: inline-block;
            background: rgba(0, 0, 0, 0.75);
            color: #fff;
            font-size: 1.1rem;
            font-weight: 500;
            padding: 0.4rem 1rem;
            border-radius: 6px;
            line-height: 1.5;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
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
                <div class="scene-list-item p-4" id="scene-card-{{ $i }}">
                    <div class="flex gap-4">
                        <div class="w-32 h-20 rounded-lg flex-shrink-0 cursor-pointer bg-gray-800 overflow-hidden" onclick="jumpToScene({{ $i }})">
                            <img id="scene-img-{{ $i }}" alt="Scene {{ $sceneNum }}" class="w-full h-full object-cover" style="opacity:0;transition:opacity 0.3s;">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="text-white text-sm font-bold">Scene {{ $sceneNum }}</span>
                                <span class="scene-badge-part {{ $partClass }}">{{ $partLabel }}</span>
                                <span class="voice-badge">{{ $voiceLabel }}</span>
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
        var audioElements = {};
        var ttsFailedForScene = {};
        var sceneTimer = null;
        var fallbackTimer = null;
        var currentUtterance = null;
        var subtitlesEnabled = true;
        var preloadedImages = {};
        var allImagesReady = false;

        function preloadAllImages() {
            var total = SCENES.length;
            var loaded = 0;
            var concurrency = 3;
            var queue = [];
            for (var i = 0; i < total; i++) queue.push(i);

            function loadNext() {
                if (queue.length === 0) return;
                var idx = queue.shift();
                var scene = SCENES[idx];
                if (!scene || !scene.image_url) {
                    loaded++;
                    updatePreloadUI(loaded, total);
                    loadNext();
                    return;
                }
                var img = new Image();
                img.onload = function() {
                    preloadedImages[idx] = img;
                    loaded++;
                    updatePreloadUI(loaded, total);
                    applyToDOM(idx, img.src);
                    loadNext();
                };
                img.onerror = function() {
                    loaded++;
                    updatePreloadUI(loaded, total);
                    loadNext();
                };
                img.src = scene.image_url;
            }

            for (var c = 0; c < Math.min(concurrency, total); c++) {
                loadNext();
            }
        }

        function applyToDOM(idx, src) {
            var thumb = document.getElementById('thumb-' + idx);
            if (thumb) { thumb.src = src; thumb.style.opacity = '1'; }
            var sceneImg = document.getElementById('scene-img-' + idx);
            if (sceneImg) { sceneImg.src = src; sceneImg.style.opacity = '1'; }
        }

        function updatePreloadUI(loaded, total) {
            var pct = Math.round((loaded / total) * 100);
            var fill = document.getElementById('preload-fill');
            var count = document.getElementById('preload-count');
            var label = document.getElementById('preload-label');
            if (fill) fill.style.width = pct + '%';
            if (count) count.textContent = loaded + ' / ' + total;
            if (loaded >= total) {
                allImagesReady = true;
                if (label) label.textContent = 'Pret !';
                setTimeout(function() {
                    var overlay = document.getElementById('preload-overlay');
                    if (overlay) overlay.classList.add('done');
                }, 400);
            }
        }

        preloadAllImages();

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

            screen.querySelectorAll('img.active-img').forEach(function(el) {
                el.classList.remove('active-img');
                KB_EFFECTS.forEach(function(k) { el.classList.remove(k); });
                el.classList.add('hidden-img');
                setTimeout(function() { el.remove(); }, 1000);
            });

            var cached = preloadedImages[index];
            if (cached) {
                var clone = cached.cloneNode();
                clone.className = 'hidden-img';
                screen.insertBefore(clone, overlay);
                clone.offsetHeight;
                clone.classList.remove('hidden-img');
                clone.classList.add('active-img');
                clone.classList.add(getKBEffect(index));
            }

            var subtitleText = document.getElementById('subtitle-text');
            var subtitleBar = document.getElementById('subtitle-bar');
            subtitleText.textContent = scene.narration || '';
            if (subtitlesEnabled) {
                subtitleBar.classList.remove('hidden-sub');
            }

            var label = document.getElementById('cinema-scene-label');
            var part = scene.part || 'development';
            label.textContent = 'Scene ' + (scene.scene_number || index + 1);
            label.className = 'scene-badge-part part-' + part;

            var voiceMap = { narratrice: 'Narratrice', narrateur: 'Narrateur', enfant_fille: 'Enfant (fille)', enfant_garcon: 'Enfant (garcon)' };
            document.getElementById('cinema-voice-badge').textContent = voiceMap[scene.voice] || 'Narratrice';

            document.getElementById('scene-counter').textContent = (index + 1) + ' / ' + SCENES.length;

            var pct = ((index + 1) / SCENES.length) * 100;
            document.getElementById('progress-fill').style.width = pct + '%';

            document.querySelectorAll('.scene-thumb, [id^="thumb-wrap-"]').forEach(function(t) {
                t.style.borderColor = 'transparent';
            });
            var thumbWrap = document.getElementById('thumb-wrap-' + index);
            if (thumbWrap) {
                thumbWrap.style.borderColor = '#a855f7';
                thumbWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }

            document.querySelectorAll('.scene-list-item').forEach(function(c) { c.classList.remove('active-scene'); });
            var card = document.getElementById('scene-card-' + index);
            if (card) card.classList.add('active-scene');

            currentScene = index;
        }

        function getAudio(index) {
            if (audioElements[index]) return audioElements[index];
            var scene = SCENES[index];
            if (!scene) return null;
            var sceneNum = scene.scene_number || (index + 1);
            var audio = new Audio();
            audio.preload = 'none';
            audio.dataset.src = '/video/' + PROJECT_ID + '/tts/' + sceneNum;
            audioElements[index] = audio;
            return audio;
        }

        function playSceneAudio(index, onEnd) {
            var scene = SCENES[index];
            if (!scene || !scene.narration) { onEnd(); return; }

            if (ttsFailedForScene[index]) {
                playBrowserTTS(index, onEnd);
                return;
            }

            var audio = getAudio(index);
            if (!audio.src || audio.src === window.location.href) {
                audio.src = audio.dataset.src;
            }

            function handleEnd() { cleanup(); onEnd(); }
            function handleError() { cleanup(); ttsFailedForScene[index] = true; playBrowserTTS(index, onEnd); }

            function cleanup() {
                audio.removeEventListener('ended', handleEnd);
                audio.removeEventListener('error', handleError);
            }

            audio.addEventListener('ended', handleEnd);
            audio.addEventListener('error', handleError);

            audio.play().catch(function() {
                cleanup();
                ttsFailedForScene[index] = true;
                playBrowserTTS(index, onEnd);
            });
        }

        function playBrowserTTS(index, onEnd) {
            if (!('speechSynthesis' in window)) { onEnd(); return; }

            var scene = SCENES[index];
            if (!scene || !scene.narration) { onEnd(); return; }

            speechSynthesis.cancel();
            var utterance = new SpeechSynthesisUtterance(scene.narration);
            utterance.lang = 'fr-FR';
            utterance.rate = 0.92;
            utterance.pitch = 1.05;

            var voices = speechSynthesis.getVoices();
            var frVoice = voices.find(function(v) { return v.lang.startsWith('fr'); });
            if (frVoice) utterance.voice = frVoice;

            currentUtterance = utterance;
            utterance.onend = function() { currentUtterance = null; onEnd(); };
            utterance.onerror = function() { currentUtterance = null; onEnd(); };
            speechSynthesis.speak(utterance);
        }

        function stopAllAudio() {
            Object.values(audioElements).forEach(function(a) { a.pause(); a.currentTime = 0; });
            if ('speechSynthesis' in window) speechSynthesis.cancel();
            currentUtterance = null;
            if (sceneTimer) { clearTimeout(sceneTimer); sceneTimer = null; }
            if (fallbackTimer) { clearTimeout(fallbackTimer); fallbackTimer = null; }
        }

        function playScene(index) {
            if (index >= SCENES.length) { stopPlayback(); return; }

            showScene(index);

            var scene = SCENES[index];
            var duration = (scene.duration_seconds || 10) * 1000;

            playSceneAudio(index, function() {
                if (!isPlaying) return;
                sceneTimer = setTimeout(function() { if (isPlaying) playScene(index + 1); }, 500);
            });

            fallbackTimer = setTimeout(function() {
                if (isPlaying && currentScene === index) {
                    stopAllAudio();
                    playScene(index + 1);
                }
            }, duration + 5000);
        }

        window.togglePlay = function() {
            if (isPlaying) {
                stopPlayback();
            } else {
                isPlaying = true;
                document.getElementById('play-icon').innerHTML = '&#9646;&#9646;';
                document.getElementById('btn-play').classList.add('active');
                if (currentScene < 0 || currentScene >= SCENES.length - 1) {
                    playScene(0);
                } else {
                    playScene(currentScene);
                }
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
            if (subtitlesEnabled) {
                bar.classList.remove('hidden-sub');
                btn.classList.add('active');
            } else {
                bar.classList.add('hidden-sub');
                btn.classList.remove('active');
            }
        };

        document.getElementById('btn-sub').classList.add('active');

        if ('speechSynthesis' in window) {
            speechSynthesis.getVoices();
            speechSynthesis.onvoiceschanged = function() { speechSynthesis.getVoices(); };
        }
    })();
    </script>
</body>
</html>