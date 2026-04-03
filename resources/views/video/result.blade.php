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

        .gradient-bg {
            background-image: url('/images/desk-bg.svg');
            background-size: cover;
            background-position: center;
        }
        .gradient-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(8, 6, 25, 0.72);
            z-index: 1;
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

        .scene-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .scene-card:hover {
            transform: translateY(-3px);
            border-color: rgba(167, 139, 250, 0.35);
        }
        .scene-card.active-scene {
            border-color: rgba(167, 139, 250, 0.6);
            box-shadow: 0 0 20px rgba(167, 139, 250, 0.15);
        }
        .scene-badge {
            background: linear-gradient(135deg, #667eea33, #764ba233);
            border: 1px solid rgba(167,139,250,0.3);
        }
        .story-block {
            background: rgba(255,255,255,0.03);
            border-left: 3px solid #7c3aed;
            line-height: 1.85;
        }

        .audio-controls {
            background: rgba(167, 139, 250, 0.08);
            border: 1px solid rgba(167, 139, 250, 0.2);
            border-radius: 10px;
        }

        .play-all-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            transition: all 0.3s ease;
        }
        .play-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.35);
        }
        .play-all-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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
<body class="gradient-bg min-h-screen py-10 px-4 relative overflow-x-hidden">

    <div class="max-w-4xl mx-auto relative z-10">

        <div class="text-center mb-8 fade-in-up">
            <span class="inline-block text-5xl mb-3">🎉</span>
            <h1 class="text-3xl font-extrabold text-white mb-2">Votre vidéo est prête !</h1>
            <p class="text-gray-400">
                « <span class="text-purple-300 font-medium">{{ $project->theme }}</span> »
            </p>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-8 fade-in-up" style="animation-delay:0.05s">
            <div class="card-glass rounded-xl p-4 text-center">
                <p class="text-2xl font-extrabold text-white">{{ $project->getSceneCount() }}</p>
                <p class="text-gray-400 text-xs mt-1 uppercase tracking-wider">Scènes</p>
            </div>
            <div class="card-glass rounded-xl p-4 text-center">
                <p class="text-2xl font-extrabold text-white">{{ $project->getTotalDuration() }}s</p>
                <p class="text-gray-400 text-xs mt-1 uppercase tracking-wider">Durée totale</p>
            </div>
            <div class="card-glass rounded-xl p-4 text-center">
                <p class="text-2xl font-extrabold text-white">#{{ $project->id }}</p>
                <p class="text-gray-400 text-xs mt-1 uppercase tracking-wider">Projet</p>
            </div>
        </div>

        <div class="mb-8 fade-in-up" style="animation-delay:0.1s">
            @php
                $anyVideo = false;
                if ($project->scenes_json) {
                    foreach ($project->scenes_json as $sc) {
                        if (!empty($sc['video_url']) && filter_var($sc['video_url'], FILTER_VALIDATE_URL)
                            && !str_contains($sc['video_url'], 'placeholder')) {
                            $anyVideo = true;
                            break;
                        }
                    }
                }
                if (!$anyVideo) {
                    $anyVideo = $project->video_url
                        && !str_contains($project->video_url, 'placeholder')
                        && !str_contains($project->video_url, 'demo-mode')
                        && filter_var($project->video_url, FILTER_VALIDATE_URL);
                }
            @endphp

            @if(!$anyVideo)
            <div class="card-glass rounded-2xl p-8 text-center mb-5">
                <span class="text-5xl mb-4 inline-block">🎥</span>
                <h3 class="text-white text-lg font-semibold mb-2">Pipeline terminé avec succès !</h3>
                <p class="text-gray-400 text-sm mb-3">
                    L'histoire et les scènes ont été générées. Les vidéos seront disponibles
                    lorsque le pipeline vidéo (Replicate) sera activé.
                </p>
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-900/30 border border-green-500/30 text-green-300 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Histoire &amp; scènes générées
                </div>
            </div>
            @endif

            {{-- Action buttons --}}
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="{{ route('video.index') }}"
                   class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Générer une nouvelle vidéo
                </a>

                @if($project->scenes_json && count($project->scenes_json) > 0)
                <button id="play-all-btn" onclick="playAll()"
                        class="play-all-btn flex-1 py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                    <span id="play-all-icon">▶</span>
                    <span id="play-all-text">Lecture complète</span>
                </button>
                @endif
            </div>

            <a href="/video/{{ $project->id }}/download"
               class="mt-4 w-full py-3 rounded-xl text-sm font-semibold flex items-center justify-center gap-2
                      bg-white/8 border border-white/15 text-gray-300 hover:bg-white/12 hover:text-white transition-all duration-200 block">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                </svg>
                Télécharger le pack complet (.zip) &mdash; histoire, script, scènes
            </a>
        </div>

        @if($project->story_text)
        <div class="card-glass rounded-2xl p-8 mb-8 fade-in-up" style="animation-delay:0.2s">
            <h2 class="text-white text-xl font-semibold mb-5 flex items-center gap-2">
                <span class="text-purple-400">📖</span>
                L'histoire complète
            </h2>
            <div class="story-block rounded-r-xl p-5 text-gray-300 text-sm">
                {!! nl2br(e($project->story_text)) !!}
            </div>
        </div>
        @endif

        @if($project->scenes_json && count($project->scenes_json) > 0)
        <div class="fade-in-up" style="animation-delay:0.3s">
            <h2 class="text-white text-xl font-semibold mb-6 flex items-center gap-2">
                <span class="text-purple-400">🎬</span>
                Les {{ count($project->scenes_json) }} scènes
            </h2>

            <div class="flex flex-col gap-6">
                @foreach($project->scenes_json as $scene)
                @php
                    $sceneNum = $scene['scene_number'] ?? ($loop->index + 1);
                    $sceneVideoUrl = $scene['video_url'] ?? '';
                    $hasSceneVideo = $sceneVideoUrl
                        && !str_contains($sceneVideoUrl, 'placeholder')
                        && filter_var($sceneVideoUrl, FILTER_VALIDATE_URL);
                @endphp
                <div class="scene-card overflow-hidden" id="scene-card-{{ $loop->index }}" data-scene-index="{{ $loop->index }}">
                    {{-- Scene header --}}
                    <div class="p-5 border-b border-white/5 flex items-center justify-between">
                        <div class="scene-badge inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold text-purple-300">
                            Scène {{ $sceneNum }}
                        </div>
                        <span class="text-gray-500 text-xs font-mono">
                            {{ $scene['duration_seconds'] ?? 15 }}s
                        </span>
                    </div>

                    {{-- Video player --}}
                    @if($hasSceneVideo)
                    <div class="bg-black">
                        <video id="video-{{ $loop->index }}" controls preload="metadata" class="w-full" style="max-height: 360px">
                            <source src="{{ $sceneVideoUrl }}" type="video/mp4">
                        </video>
                    </div>
                    @endif

                    <div class="p-5">
                        @if(!empty($scene['visual_description']))
                        <div class="mb-3">
                            <p class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-1">Visuel</p>
                            <p class="text-gray-400 text-xs leading-relaxed italic">
                                {{ $scene['visual_description'] }}
                            </p>
                        </div>
                        @endif

                        @if(!empty($scene['narration']))
                        <div class="mb-3">
                            <p class="text-xs uppercase tracking-wider text-gray-500 font-medium mb-1">Narration</p>
                            <p class="text-gray-200 text-sm leading-relaxed">
                                {{ $scene['narration'] }}
                            </p>
                        </div>

                        {{-- ElevenLabs audio player --}}
                        <div class="audio-controls p-3 mt-3">
                            <div class="flex items-center gap-3">
                                <button onclick="toggleAudio({{ $loop->index }})"
                                        id="audio-btn-{{ $loop->index }}"
                                        class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-600/40 hover:bg-purple-600/60 flex items-center justify-center text-purple-200 transition-all">
                                    <span id="audio-icon-{{ $loop->index }}">🔊</span>
                                </button>
                                <div class="flex-1">
                                    <audio id="audio-{{ $loop->index }}"
                                           preload="none"
                                           data-src="/video/{{ $project->id }}/tts/{{ $sceneNum }}">
                                    </audio>
                                    <p class="text-purple-300 text-xs" id="audio-status-{{ $loop->index }}">
                                        Voix ElevenLabs — cliquer pour écouter
                                    </p>
                                    <div class="w-full bg-purple-900/30 rounded-full h-1 mt-1.5">
                                        <div id="audio-progress-{{ $loop->index }}" class="bg-purple-400 h-1 rounded-full transition-all" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="mt-10 text-center text-gray-400 text-xs pb-4">
            Fait par Julien YILDIZ &mdash; rendu test de stage &mdash; #{{ $project->id }}
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        const SCENE_COUNT = {{ count($project->scenes_json ?? []) }};
        const audioElements = {};
        let currentPlayingIndex = null;
        let isPlayingAll = false;
        let playAllIndex = 0;

        // Lazy-load and toggle audio for a single scene
        window.toggleAudio = function(index) {
            const audio = getAudio(index);
            if (!audio) return;

            // If another scene is playing, stop it
            if (currentPlayingIndex !== null && currentPlayingIndex !== index) {
                stopAudio(currentPlayingIndex);
            }

            if (!audio.paused) {
                audio.pause();
                audio.currentTime = 0;
                setAudioState(index, 'ready');
                currentPlayingIndex = null;
            } else {
                audio.play().then(() => {
                    setAudioState(index, 'playing');
                    currentPlayingIndex = index;
                    highlightScene(index);
                }).catch(err => {
                    setAudioState(index, 'error');
                    console.error('Audio play error:', err);
                });
            }
        };

        function getAudio(index) {
            if (audioElements[index]) return audioElements[index];

            const el = document.getElementById('audio-' + index);
            if (!el) return null;

            // Lazy load src
            if (!el.src || el.src === '' || el.src === window.location.href) {
                const dataSrc = el.getAttribute('data-src');
                if (dataSrc) {
                    el.src = dataSrc;
                    el.load();
                    setAudioState(index, 'loading');
                }
            }

            el.addEventListener('ended', () => {
                setAudioState(index, 'ready');
                currentPlayingIndex = null;
                if (isPlayingAll) {
                    playAllIndex++;
                    playNextScene();
                }
            });

            el.addEventListener('timeupdate', () => {
                if (el.duration) {
                    const pct = (el.currentTime / el.duration) * 100;
                    const bar = document.getElementById('audio-progress-' + index);
                    if (bar) bar.style.width = pct + '%';
                }
            });

            el.addEventListener('canplaythrough', () => {
                if (audioElements[index] && audioElements[index].paused) {
                    setAudioState(index, 'ready');
                }
            }, { once: true });

            el.addEventListener('error', () => {
                setAudioState(index, 'error');
            });

            audioElements[index] = el;
            return el;
        }

        function stopAudio(index) {
            const audio = audioElements[index];
            if (audio && !audio.paused) {
                audio.pause();
                audio.currentTime = 0;
            }
            setAudioState(index, 'ready');
            const bar = document.getElementById('audio-progress-' + index);
            if (bar) bar.style.width = '0%';
        }

        function setAudioState(index, state) {
            const icon = document.getElementById('audio-icon-' + index);
            const status = document.getElementById('audio-status-' + index);
            if (!icon || !status) return;

            switch (state) {
                case 'loading':
                    icon.textContent = '⏳';
                    status.textContent = 'Chargement de la voix...';
                    break;
                case 'playing':
                    icon.textContent = '⏹️';
                    status.textContent = 'Lecture en cours...';
                    break;
                case 'ready':
                    icon.textContent = '🔊';
                    status.textContent = 'Voix ElevenLabs — cliquer pour écouter';
                    break;
                case 'error':
                    icon.textContent = '⚠️';
                    status.textContent = 'Erreur de chargement audio';
                    break;
            }
        }

        function highlightScene(index) {
            document.querySelectorAll('.scene-card').forEach(c => c.classList.remove('active-scene'));
            const card = document.getElementById('scene-card-' + index);
            if (card) {
                card.classList.add('active-scene');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Play All: sequential video + audio for each scene
        window.playAll = function() {
            if (isPlayingAll) {
                stopPlayAll();
                return;
            }

            isPlayingAll = true;
            playAllIndex = 0;
            updatePlayAllBtn(true);

            // Stop any currently playing audio
            if (currentPlayingIndex !== null) {
                stopAudio(currentPlayingIndex);
                currentPlayingIndex = null;
            }

            playNextScene();
        };

        function playNextScene() {
            if (playAllIndex >= SCENE_COUNT || !isPlayingAll) {
                stopPlayAll();
                return;
            }

            highlightScene(playAllIndex);
            const idx = playAllIndex;

            // Try to play video first
            const video = document.getElementById('video-' + idx);
            if (video) {
                video.currentTime = 0;
                video.play().catch(() => {});
            }

            // Play audio (ElevenLabs)
            const audio = getAudio(idx);
            if (audio) {
                if (!audio.src || audio.src === '' || audio.src === window.location.href) {
                    const dataSrc = audio.getAttribute('data-src');
                    if (dataSrc) {
                        audio.src = dataSrc;
                        audio.load();
                    }
                }
                audio.currentTime = 0;
                // Wait a moment for load if needed then play
                const tryPlay = () => {
                    audio.play().then(() => {
                        setAudioState(idx, 'playing');
                        currentPlayingIndex = idx;
                    }).catch(() => {
                        // If can't play audio, move to next after a delay
                        setTimeout(() => {
                            playAllIndex++;
                            playNextScene();
                        }, 3000);
                    });
                };

                if (audio.readyState >= 2) {
                    tryPlay();
                } else {
                    setAudioState(idx, 'loading');
                    audio.addEventListener('canplaythrough', tryPlay, { once: true });
                    // Safety timeout
                    setTimeout(() => {
                        if (audio.readyState < 2 && isPlayingAll && playAllIndex === idx) {
                            tryPlay();
                        }
                    }, 5000);
                }
            } else {
                // No audio, wait scene duration then next
                setTimeout(() => {
                    if (isPlayingAll) {
                        playAllIndex++;
                        playNextScene();
                    }
                }, 5000);
            }
        }

        function stopPlayAll() {
            isPlayingAll = false;
            playAllIndex = 0;
            if (currentPlayingIndex !== null) {
                stopAudio(currentPlayingIndex);
                currentPlayingIndex = null;
            }
            document.querySelectorAll('.scene-card').forEach(c => c.classList.remove('active-scene'));
            updatePlayAllBtn(false);
        }

        function updatePlayAllBtn(playing) {
            const icon = document.getElementById('play-all-icon');
            const text = document.getElementById('play-all-text');
            if (!icon || !text) return;
            if (playing) {
                icon.textContent = '⏹';
                text.textContent = 'Arrêter la lecture';
            } else {
                icon.textContent = '▶';
                text.textContent = 'Lecture complète';
            }
        }
    })();
    </script>
</body>
</html>
