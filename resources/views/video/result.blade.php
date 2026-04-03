<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $project->theme }} — AI Kids Video</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Inter', sans-serif; }

        body {
            background: #0a0618;
            color: #e5e7eb;
        }
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

        /* Cinema player */
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

        /* Ken Burns zoom effect */
        @keyframes kenburns {
            0% { transform: scale(1) translate(0, 0); }
            100% { transform: scale(1.08) translate(-1%, -1%); }
        }
        .cinema-screen img.active-img {
            animation: kenburns 15s ease-in-out forwards;
        }

        .cinema-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.85));
            padding: 2rem 1.5rem 1.5rem;
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

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #4c1d95; border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen py-8 px-4">

    <div class="max-w-5xl mx-auto">

        {{-- Header --}}
        <div class="text-center mb-6 fade-in-up">
            <h1 class="text-3xl font-extrabold text-white mb-2">{{ $project->theme }}</h1>
            <p class="text-gray-400 text-sm">
                {{ $project->getSceneCount() }} scènes &middot; {{ $project->getTotalDuration() }}s &middot; Projet #{{ $project->id }}
            </p>
        </div>

        @php
            $scenes = $project->scenes_json ?? [];
            $voiceLabels = [
                'narratrice' => 'Narratrice',
                'narrateur' => 'Narrateur',
                'enfant_fille' => 'Enfant (fille)',
                'enfant_garcon' => 'Enfant (garçon)',
            ];
        @endphp

        {{-- Cinema Player --}}
        @if(count($scenes) > 0)
        <div class="cinema-container mb-6 fade-in-up" style="animation-delay:0.1s">
            <div class="cinema-screen" id="cinema-screen">
                {{-- Images loaded dynamically --}}
                <div id="cinema-placeholder" class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-purple-900/50 to-indigo-900/50">
                    <div class="text-center">
                        <span class="text-6xl mb-4 block">🎬</span>
                        <p class="text-white text-lg font-semibold">Cliquez sur Play pour lancer le film</p>
                        <p class="text-gray-400 text-sm mt-2">{{ count($scenes) }} scènes illustrées avec narration IA</p>
                    </div>
                </div>

                {{-- Narration overlay --}}
                <div class="cinema-overlay" id="cinema-overlay" style="display:none">
                    <p id="cinema-narration" class="text-white text-lg font-medium leading-relaxed mb-2"></p>
                    <div class="flex items-center gap-3">
                        <span id="cinema-scene-label" class="scene-badge-part part-introduction">Scène 1</span>
                        <span id="cinema-voice-badge" class="voice-badge"></span>
                    </div>
                </div>
            </div>

            {{-- Controls --}}
            <div class="cinema-controls">
                <button class="ctrl-btn" id="btn-play" onclick="togglePlay()" title="Lecture / Pause">
                    <span id="play-icon">&#9654;</span>
                </button>
                <div class="progress-bar" id="progress-bar" onclick="seekProgress(event)">
                    <div class="progress-fill" id="progress-fill" style="width:0%"></div>
                </div>
                <span class="text-gray-400 text-xs font-mono whitespace-nowrap" id="scene-counter">0 / {{ count($scenes) }}</span>
                <button class="ctrl-btn" id="btn-restart" onclick="restartFilm()" title="Recommencer">&#8634;</button>
            </div>

            {{-- Scene thumbnails strip --}}
            <div class="flex gap-2 p-3 overflow-x-auto" id="thumb-strip">
                @foreach($scenes as $i => $scene)
                @php
                    $imgUrl = $scene['image_url'] ?? '';
                @endphp
                <img src="{{ $imgUrl }}"
                     alt="Scène {{ $scene['scene_number'] ?? $i+1 }}"
                     class="scene-thumb"
                     id="thumb-{{ $i }}"
                     onclick="jumpToScene({{ $i }})"
                     loading="lazy">
                @endforeach
            </div>
        </div>
        @endif

        {{-- Action buttons --}}
        <div class="flex flex-col sm:flex-row gap-4 mb-8 fade-in-up" style="animation-delay:0.15s">
            <a href="{{ route('video.index') }}"
               class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                + Générer un nouveau film
            </a>
            <a href="/video/{{ $project->id }}/download"
               class="flex-1 py-3 rounded-xl text-sm font-semibold flex items-center justify-center gap-2
                      bg-white/8 border border-white/15 text-gray-300 hover:bg-white/12 hover:text-white transition-all duration-200">
                Télécharger le pack complet (.zip)
            </a>
        </div>

        {{-- Story --}}
        @if($project->story_text)
        <div class="card-glass rounded-2xl p-6 mb-8 fade-in-up" style="animation-delay:0.2s">
            <h2 class="text-white text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="text-purple-400">📖</span> L'histoire complète
            </h2>
            <div class="story-block rounded-r-xl p-4 text-gray-300 text-sm">
                {!! nl2br(e($project->story_text)) !!}
            </div>
        </div>
        @endif

        {{-- All scenes list --}}
        @if(count($scenes) > 0)
        <div class="fade-in-up" style="animation-delay:0.3s">
            <h2 class="text-white text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="text-purple-400">🎬</span> Les {{ count($scenes) }} scènes
            </h2>
            <div class="flex flex-col gap-4">
                @foreach($scenes as $i => $scene)
                @php
                    $sceneNum = $scene['scene_number'] ?? ($i + 1);
                    $part = $scene['part'] ?? 'development';
                    $partClass = match($part) { 'introduction' => 'part-introduction', 'conclusion' => 'part-conclusion', default => 'part-development' };
                    $partLabel = match($part) { 'introduction' => 'Introduction', 'conclusion' => 'Conclusion', default => 'Développement' };
                    $voiceType = $scene['voice'] ?? 'narratrice';
                    $voiceLabel = $voiceLabels[$voiceType] ?? 'Narratrice';
                    $imgUrl = $scene['image_url'] ?? '';
                @endphp
                <div class="scene-list-item p-4" id="scene-card-{{ $i }}">
                    <div class="flex gap-4">
                        @if($imgUrl)
                        <img src="{{ $imgUrl }}" alt="Scène {{ $sceneNum }}"
                             class="w-32 h-20 object-cover rounded-lg flex-shrink-0 cursor-pointer"
                             onclick="jumpToScene({{ $i }})"
                             loading="lazy">
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-white text-sm font-bold">Scène {{ $sceneNum }}</span>
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
            Fait par Julien YILDIZ &mdash; AI Kids Video Generator &mdash; #{{ $project->id }}
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        const SCENES = @json($scenes);
        const PROJECT_ID = {{ $project->id }};

        let currentScene = -1;
        let isPlaying = false;
        let audioElements = {};
        let ttsFailedForScene = {};
        let sceneTimer = null;
        let currentUtterance = null;

        // ─── Image management ───
        const imgCache = {};

        function getSceneImage(index) {
            if (imgCache[index]) return imgCache[index];
            const scene = SCENES[index];
            if (!scene || !scene.image_url) return null;
            const img = new Image();
            img.src = scene.image_url;
            img.className = 'hidden-img';
            imgCache[index] = img;
            return img;
        }

        // Preload first 3 images
        for (let i = 0; i < Math.min(3, SCENES.length); i++) {
            getSceneImage(i);
        }

        // ─── Cinema display ───
        function showScene(index) {
            if (index < 0 || index >= SCENES.length) return;

            const screen = document.getElementById('cinema-screen');
            const placeholder = document.getElementById('cinema-placeholder');
            const overlay = document.getElementById('cinema-overlay');
            const scene = SCENES[index];

            // Hide placeholder
            if (placeholder) placeholder.style.display = 'none';
            overlay.style.display = '';

            // Remove old active images
            screen.querySelectorAll('img.active-img').forEach(img => {
                img.classList.remove('active-img');
                img.classList.add('hidden-img');
                setTimeout(() => img.remove(), 1000);
            });

            // Add new image
            const img = getSceneImage(index);
            if (img) {
                const clone = img.cloneNode();
                clone.className = 'hidden-img';
                screen.insertBefore(clone, overlay);
                // Force reflow then animate
                clone.offsetHeight;
                clone.classList.remove('hidden-img');
                clone.classList.add('active-img');
            }

            // Update narration + badges
            document.getElementById('cinema-narration').textContent = scene.narration || '';
            const label = document.getElementById('cinema-scene-label');
            const part = scene.part || 'development';
            label.textContent = 'Scène ' + (scene.scene_number || index + 1);
            label.className = 'scene-badge-part part-' + part;

            const voiceMap = { narratrice: 'Narratrice', narrateur: 'Narrateur', enfant_fille: 'Enfant (fille)', enfant_garcon: 'Enfant (garçon)' };
            document.getElementById('cinema-voice-badge').textContent = voiceMap[scene.voice] || 'Narratrice';

            // Update counter
            document.getElementById('scene-counter').textContent = (index + 1) + ' / ' + SCENES.length;

            // Update progress
            const pct = ((index + 1) / SCENES.length) * 100;
            document.getElementById('progress-fill').style.width = pct + '%';

            // Update thumbnails
            document.querySelectorAll('.scene-thumb').forEach(t => t.classList.remove('active-thumb'));
            const thumb = document.getElementById('thumb-' + index);
            if (thumb) {
                thumb.classList.add('active-thumb');
                thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }

            // Highlight scene card
            document.querySelectorAll('.scene-list-item').forEach(c => c.classList.remove('active-scene'));
            const card = document.getElementById('scene-card-' + index);
            if (card) card.classList.add('active-scene');

            currentScene = index;

            // Preload next 2 images
            getSceneImage(index + 1);
            getSceneImage(index + 2);
        }

        // ─── Audio (ElevenLabs with browser TTS fallback) ───
        function getAudio(index) {
            if (audioElements[index]) return audioElements[index];
            const scene = SCENES[index];
            if (!scene) return null;
            const sceneNum = scene.scene_number || (index + 1);
            const audio = new Audio();
            audio.preload = 'none';
            audio.dataset.src = '/video/' + PROJECT_ID + '/tts/' + sceneNum;
            audioElements[index] = audio;
            return audio;
        }

        function playSceneAudio(index, onEnd) {
            const scene = SCENES[index];
            if (!scene || !scene.narration) {
                onEnd();
                return;
            }

            if (ttsFailedForScene[index]) {
                playBrowserTTS(index, onEnd);
                return;
            }

            const audio = getAudio(index);
            if (!audio.src || audio.src === window.location.href) {
                audio.src = audio.dataset.src;
            }

            const handleEnd = () => { cleanup(); onEnd(); };
            const handleError = () => {
                cleanup();
                ttsFailedForScene[index] = true;
                playBrowserTTS(index, onEnd);
            };

            function cleanup() {
                audio.removeEventListener('ended', handleEnd);
                audio.removeEventListener('error', handleError);
            }

            audio.addEventListener('ended', handleEnd);
            audio.addEventListener('error', handleError);

            audio.play().catch(() => {
                cleanup();
                ttsFailedForScene[index] = true;
                playBrowserTTS(index, onEnd);
            });
        }

        function playBrowserTTS(index, onEnd) {
            if (!('speechSynthesis' in window)) { onEnd(); return; }

            const scene = SCENES[index];
            if (!scene || !scene.narration) { onEnd(); return; }

            speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(scene.narration);
            utterance.lang = 'fr-FR';
            utterance.rate = 0.92;
            utterance.pitch = 1.05;

            const voices = speechSynthesis.getVoices();
            const frVoice = voices.find(v => v.lang.startsWith('fr'));
            if (frVoice) utterance.voice = frVoice;

            currentUtterance = utterance;
            utterance.onend = () => { currentUtterance = null; onEnd(); };
            utterance.onerror = () => { currentUtterance = null; onEnd(); };
            speechSynthesis.speak(utterance);
        }

        function stopAllAudio() {
            Object.values(audioElements).forEach(a => { a.pause(); a.currentTime = 0; });
            speechSynthesis.cancel();
            currentUtterance = null;
            if (sceneTimer) { clearTimeout(sceneTimer); sceneTimer = null; }
        }

        // ─── Playback control ───
        function playScene(index) {
            if (index >= SCENES.length) {
                stopPlayback();
                return;
            }

            showScene(index);

            const scene = SCENES[index];
            const duration = (scene.duration_seconds || 10) * 1000;

            playSceneAudio(index, () => {
                if (!isPlaying) return;
                // If audio finished before scene duration, wait remaining
                // If audio took longer, move on immediately
                const next = () => { if (isPlaying) playScene(index + 1); };
                // Small gap between scenes
                sceneTimer = setTimeout(next, 500);
            });

            // Fallback: if audio takes too long, advance after scene duration + buffer
            const maxWait = duration + 5000; // scene duration (ms) + 5s buffer
            sceneTimer = setTimeout(() => {
                if (isPlaying && currentScene === index) {
                    stopAllAudio();
                    playScene(index + 1);
                }
            }, maxWait);
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
            if (isPlaying) {
                playScene(index);
            }
        };

        window.seekProgress = function(e) {
            const bar = document.getElementById('progress-bar');
            const rect = bar.getBoundingClientRect();
            const pct = (e.clientX - rect.left) / rect.width;
            const target = Math.floor(pct * SCENES.length);
            jumpToScene(Math.max(0, Math.min(target, SCENES.length - 1)));
        };

        // Preload browser voices
        if ('speechSynthesis' in window) {
            speechSynthesis.getVoices();
            speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();
        }
    })();
    </script>
</body>
</html>
