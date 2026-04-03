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
        .btn-download {
            background: linear-gradient(135deg, #059669, #10b981);
            transition: all 0.3s ease;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.35);
        }

        .video-wrapper {
            position: relative;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.1);
        }
        video {
            display: block;
            width: 100%;
            max-height: 480px;
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
        .scene-badge {
            background: linear-gradient(135deg, #667eea33, #764ba233);
            border: 1px solid rgba(167,139,250,0.3);
        }
        .story-block {
            background: rgba(255,255,255,0.03);
            border-left: 3px solid #7c3aed;
            line-height: 1.85;
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
                « <span class="text-purple-300 font-medium">{{ $project->theme }}</span> »
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

            {{-- Boutons d'action --}}
            <div class="flex flex-col sm:flex-row gap-4">
                <a
                    href="{{ route('video.index') }}"
                    class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold text-sm flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4v16m8-8H4"/>
                    </svg>
                    Générer une nouvelle vidéo
                </a>
            </div>

            <a
                href="/video/{{ $project->id }}/download"
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
                    $sceneVideoUrl = $scene['video_url'] ?? '';
                    $hasSceneVideo = $sceneVideoUrl
                        && !str_contains($sceneVideoUrl, 'placeholder')
                        && filter_var($sceneVideoUrl, FILTER_VALIDATE_URL);
                @endphp
                <div class="scene-card overflow-hidden">
                    {{-- Scene header --}}
                    <div class="p-5 border-b border-white/5 flex items-center justify-between">
                        <div class="scene-badge inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold text-purple-300">
                            Scène {{ $scene['scene_number'] ?? ($loop->index + 1) }}
                        </div>
                        <span class="text-gray-500 text-xs font-mono">
                            {{ $scene['duration_seconds'] ?? 15 }}s
                        </span>
                    </div>

                    {{-- Video player per scene --}}
                    @if($hasSceneVideo)
                    <div class="bg-black">
                        <video controls preload="metadata" class="w-full" style="max-height: 360px">
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

                        {{-- TTS button --}}
                        <button
                            onclick="toggleSpeech(this, {{ $loop->index }})"
                            class="tts-btn inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-purple-900/30 border border-purple-500/30 text-purple-300 text-sm hover:bg-purple-900/50 transition-all cursor-pointer">
                            <span class="tts-icon">🔊</span>
                            <span class="tts-label">Lire la narration</span>
                        </button>
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

        // Narration texts for TTS
        const sceneTexts = @json(collect($project->scenes_json ?? [])->pluck('narration')->toArray());

        let currentUtterance = null;
        let currentBtn = null;

        window.toggleSpeech = function(btn, index) {
            if (!('speechSynthesis' in window)) {
                alert('Votre navigateur ne supporte pas la synthèse vocale.');
                return;
            }

            const text = sceneTexts[index] || '';
            if (!text) return;

            // If same button is playing, stop it
            if (currentBtn === btn && speechSynthesis.speaking) {
                speechSynthesis.cancel();
                resetBtn(btn);
                currentBtn = null;
                return;
            }

            // Stop any current speech
            speechSynthesis.cancel();
            if (currentBtn) resetBtn(currentBtn);

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'fr-FR';
            utterance.rate = 0.92;
            utterance.pitch = 1.05;

            // Try to find a French voice
            const voices = speechSynthesis.getVoices();
            const frVoice = voices.find(v => v.lang.startsWith('fr'));
            if (frVoice) utterance.voice = frVoice;

            btn.querySelector('.tts-icon').textContent = '⏹️';
            btn.querySelector('.tts-label').textContent = 'Arrêter';
            btn.classList.add('bg-purple-800/50', 'border-purple-400/50');
            currentBtn = btn;

            utterance.onend = function() { resetBtn(btn); currentBtn = null; };
            utterance.onerror = function() { resetBtn(btn); currentBtn = null; };

            speechSynthesis.speak(utterance);
        };

        function resetBtn(btn) {
            if (!btn) return;
            btn.querySelector('.tts-icon').textContent = '🔊';
            btn.querySelector('.tts-label').textContent = 'Lire la narration';
            btn.classList.remove('bg-purple-800/50', 'border-purple-400/50');
        }

        // Preload voices (some browsers need this)
        if ('speechSynthesis' in window) {
            speechSynthesis.getVoices();
            speechSynthesis.onvoiceschanged = function() { speechSynthesis.getVoices(); };
        }
    })();
    </script>
</body>
</html>
