<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Kids Video Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }

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
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .step-icon { transition: all 0.5s ease; }
        .step-waiting  { color: #6b7280; }
        .step-active   { color: #a78bfa; }
        .step-done     { color: #34d399; }
        .step-error    { color: #f87171; }
        .step-line { transition: background-color 0.5s ease; }
        .step-line-done   { background-color: #34d399; }
        .step-line-active { background: linear-gradient(90deg, #34d399, #a78bfa); }

        @keyframes pulse-ring {
            0%   { box-shadow: 0 0 0 0 rgba(167, 139, 250, 0.6); }
            70%  { box-shadow: 0 0 0 12px rgba(167, 139, 250, 0); }
            100% { box-shadow: 0 0 0 0 rgba(167, 139, 250, 0); }
        }
        .step-active-ring { animation: pulse-ring 1.5s ease-out infinite; }

        @keyframes spin-smooth { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .spin { animation: spin-smooth 1.2s linear infinite; }

        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fade-in-up 0.5s ease forwards; }

        .particle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.15;
            animation: float 8s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50%       { transform: translateY(-20px) rotate(180deg); }
        }

        .style-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .style-card:hover { transform: translateY(-2px); border-color: rgba(167, 139, 250, 0.4); }
        .style-card.selected { border-color: #a78bfa; background: rgba(167, 139, 250, 0.15); }
    </style>
</head>
<body class="gradient-bg min-h-screen flex flex-col items-center justify-center p-4 relative overflow-x-hidden">

    <div class="particle w-64 h-64 bg-purple-600 top-10 left-10" style="animation-delay:0s"></div>
    <div class="particle w-48 h-48 bg-indigo-500 bottom-20 right-20" style="animation-delay:3s"></div>
    <div class="particle w-32 h-32 bg-violet-500 top-1/2 left-5" style="animation-delay:5s"></div>

    <div class="w-full max-w-2xl relative z-10">

        <div class="text-center mb-10 fade-in-up">
            <div class="inline-block mb-4">
                <span class="text-6xl"></span>
            </div>
            <h1 class="text-4xl font-extrabold text-white mb-2">
                AI Kids
                <span class="bg-gradient-to-r from-purple-400 to-indigo-400 bg-clip-text text-transparent">
                    Video Generator
                </span>
            </h1>
            <p class="text-gray-400 text-lg">Transformez un theme en film anime illustre pour enfants avec narration IA</p>
        </div>

        @if(session('info'))
            <div class="card-glass rounded-xl p-4 mb-6 border border-indigo-500/30 text-indigo-300 text-sm">
                {{ session('info') }}
            </div>
        @endif

        @if(session('error'))
            <div class="card-glass rounded-xl p-4 mb-6 border border-red-500/30 text-red-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <div id="form-section" class="card-glass rounded-2xl p-8 fade-in-up" style="animation-delay:0.2s">
            <h2 class="text-white text-xl font-semibold mb-6 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                Configurez votre film
            </h2>

            <form id="generate-form">
                <div class="mb-6">
                    <label for="theme" class="block text-gray-300 text-sm font-medium mb-2">
                        Theme de l'histoire
                    </label>
                    <input
                        type="text"
                        id="theme"
                        name="theme"
                        placeholder="Ex : Un petit robot qui apprend a faire des amis dans la foret enchantee"
                        class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-4 text-white placeholder-gray-500
                               focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent
                               transition-all duration-200 text-base"
                        maxlength="255"
                        required
                        autocomplete="off"
                    >
                    <div class="mt-2 flex justify-between items-center">
                        <p class="text-gray-500 text-xs">Entre 3 et 255 caracteres. Soyez creatifs !</p>
                        <p class="text-xs font-mono"><span id="char-count" class="text-gray-500">0</span><span class="text-gray-600">/255</span></p>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-300 text-sm font-medium mb-3">Style visuel</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="style-card selected card-glass rounded-xl p-3 text-center" data-style="cartoon">
                            <span class="text-2xl block mb-1"></span>
                            <span class="text-white text-xs font-semibold">Cartoon</span>
                        </div>
                        <div class="style-card card-glass rounded-xl p-3 text-center" data-style="watercolor">
                            <span class="text-2xl block mb-1"></span>
                            <span class="text-white text-xs font-semibold">Aquarelle</span>
                        </div>
                        <div class="style-card card-glass rounded-xl p-3 text-center" data-style="pixel">
                            <span class="text-2xl block mb-1"></span>
                            <span class="text-white text-xs font-semibold">Pixel Art</span>
                        </div>
                        <div class="style-card card-glass rounded-xl p-3 text-center" data-style="anime">
                            <span class="text-2xl block mb-1"></span>
                            <span class="text-white text-xs font-semibold">Anime</span>
                        </div>
                    </div>
                    <input type="hidden" id="style" name="style" value="cartoon">
                </div>

                <div id="form-error" class="hidden mb-4 p-3 bg-red-900/40 border border-red-500/40 rounded-lg text-red-300 text-sm"></div>

                <button type="submit" id="submit-btn"
                        class="btn-primary w-full py-4 rounded-xl text-white font-semibold text-base flex items-center justify-center gap-3">
                    <span id="btn-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <span id="btn-text">Generer le film</span>
                </button>
            </form>
        </div>

        <div id="pipeline-section" class="hidden fade-in-up" style="animation-delay:0.1s">

            <div class="card-glass rounded-2xl p-5 mb-6 flex items-center gap-4">
                <span class="text-2xl flex-shrink-0"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-400 text-xs uppercase tracking-wider font-medium">Theme en cours</p>
                    <p id="pipeline-theme" class="text-white font-semibold mt-1 truncate"></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-gray-500 text-xs uppercase tracking-wider font-medium">Temps ecoule</p>
                    <p id="elapsed-time" class="text-purple-300 font-mono font-bold mt-1">0s</p>
                </div>
            </div>

            <div class="card-glass rounded-2xl p-8">
                <h2 class="text-white text-lg font-semibold mb-8 text-center">Suivi du pipeline de generation</h2>

                <div class="flex flex-col gap-0">
                    <div class="step-item flex items-start gap-4" data-step="1">
                        <div class="flex flex-col items-center">
                            <div class="step-icon step-waiting w-12 h-12 rounded-full border-2 border-current flex items-center justify-center text-xl font-bold flex-shrink-0">
                                <span class="step-num">1</span>
                                <svg class="step-spinner hidden spin w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span class="step-check hidden"></span>
                            </div>
                            <div class="step-line w-0.5 h-8 bg-gray-700 my-1"></div>
                        </div>
                        <div class="pt-2 pb-6">
                            <p class="text-white font-medium">Generation de l'histoire</p>
                            <p class="text-gray-400 text-sm mt-1">L'IA (Groq) ecrit un script complet avec introduction, developpement et conclusion</p>
                        </div>
                    </div>

                    <div class="step-item flex items-start gap-4" data-step="2">
                        <div class="flex flex-col items-center">
                            <div class="step-icon step-waiting w-12 h-12 rounded-full border-2 border-current flex items-center justify-center text-xl font-bold flex-shrink-0">
                                <span class="step-num">2</span>
                                <svg class="step-spinner hidden spin w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span class="step-check hidden"></span>
                            </div>
                            <div class="step-line w-0.5 h-8 bg-gray-700 my-1"></div>
                        </div>
                        <div class="pt-2 pb-6">
                            <p class="text-white font-medium">Creation des scenes illustrees</p>
                            <p class="text-gray-400 text-sm mt-1">10 a 15 scenes avec descriptions visuelles et narration en francais</p>
                        </div>
                    </div>

                    <div class="step-item flex items-start gap-4" data-step="3">
                        <div class="flex flex-col items-center">
                            <div class="step-icon step-waiting w-12 h-12 rounded-full border-2 border-current flex items-center justify-center text-xl font-bold flex-shrink-0">
                                <span class="step-num">3</span>
                                <svg class="step-spinner hidden spin w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span class="step-check hidden"></span>
                            </div>
                            <div class="step-line w-0.5 h-8 bg-gray-700 my-1"></div>
                        </div>
                        <div class="pt-2 pb-6">
                            <p class="text-white font-medium">Generation des illustrations</p>
                            <p class="text-gray-400 text-sm mt-1">Images generees par Pollinations.ai dans le style choisi</p>
                        </div>
                    </div>

                    <div class="step-item flex items-start gap-4" data-step="4">
                        <div class="flex flex-col items-center">
                            <div class="step-icon step-waiting w-12 h-12 rounded-full border-2 border-current flex items-center justify-center text-xl font-bold flex-shrink-0">
                                <span class="step-num">4</span>
                                <svg class="step-spinner hidden spin w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span class="step-check hidden"></span>
                            </div>
                            <div class="step-line w-0.5 h-8 bg-gray-700 my-1"></div>
                        </div>
                        <div class="pt-2 pb-6">
                            <p class="text-white font-medium">Synthese vocale</p>
                            <p class="text-gray-400 text-sm mt-1">Voix dynamiques ElevenLabs adaptees au contexte de chaque scene</p>
                        </div>
                    </div>

                    <div class="step-item flex items-start gap-4" data-step="5">
                        <div class="flex flex-col items-center">
                            <div class="step-icon step-waiting w-12 h-12 rounded-full border-2 border-current flex items-center justify-center text-xl font-bold flex-shrink-0">
                                <span class="step-num">5</span>
                                <svg class="step-spinner hidden spin w-6 h-6" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <span class="step-check hidden"></span>
                            </div>
                        </div>
                        <div class="pt-2">
                            <p class="text-white font-medium">Film anime pret !</p>
                            <p class="text-gray-400 text-sm mt-1">Assemblage de toutes les scenes en un film interactif avec narration</p>
                        </div>
                    </div>
                </div>

                <div id="status-message" class="mt-8 text-center text-gray-400 text-sm italic">
                    Initialisation du pipeline...
                </div>

                <div id="pipeline-error" class="hidden mt-6 p-4 bg-red-900/40 border border-red-500/40 rounded-xl">
                    <p class="text-red-300 font-medium mb-1">Le pipeline a rencontre une erreur</p>
                    <p id="pipeline-error-msg" class="text-red-400 text-sm"></p>
                    <button onclick="location.reload()"
                            class="mt-3 px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg text-sm transition">
                        Reessayer
                    </button>
                </div>
            </div>
        </div>

    </div>

    <footer class="mt-12 text-gray-400 text-xs text-center relative z-10">
        Fait par Julien YILDIZ
    </footer>

    <script>
    (function () {
        'use strict';

        const POLL_INTERVAL = 3000;
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

        let pollTimer = null;
        let timerInterval = null;
        let projectId = null;
        let startedAt = null;
        let selectedStyle = 'cartoon';
        let pollFailureCount = 0;
        const MAX_POLL_FAILURES = 5;

        const themeInput = document.getElementById('theme');
        const charCount = document.getElementById('char-count');
        if (themeInput && charCount) {
            themeInput.addEventListener('input', function() {
                charCount.textContent = themeInput.value.length;
                charCount.className = themeInput.value.length > 230 ? 'text-yellow-400 font-medium' : 'text-gray-500';
            });
        }

        document.querySelectorAll('.style-card').forEach(function(card) {
            card.addEventListener('click', function() {
                document.querySelectorAll('.style-card').forEach(function(c) { c.classList.remove('selected'); });
                card.classList.add('selected');
                selectedStyle = card.dataset.style;
                document.getElementById('style').value = selectedStyle;
            });
        });

        document.getElementById('generate-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            var theme = document.getElementById('theme').value.trim();
            var errDiv = document.getElementById('form-error');
            errDiv.classList.add('hidden');

            if (theme.length < 3) {
                errDiv.textContent = 'Le theme doit contenir au moins 3 caracteres.';
                errDiv.classList.remove('hidden');
                return;
            }

            var btn = document.getElementById('submit-btn');
            var btnText = document.getElementById('btn-text');
            btn.disabled = true;
            btnText.textContent = 'Lancement...';

            try {
                var res = await fetch('{{ route("video.generate") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ theme: theme, style: selectedStyle }),
                });

                if (res.status === 419) {
                    window.location.reload();
                    return;
                }

                var raw = await res.text();
                var data;
                try {
                    data = raw ? JSON.parse(raw) : {};
                } catch (parseErr) {
                    data = { message: raw || 'Erreur serveur.' };
                }

                if (!res.ok || !data.success) {
                    throw new Error(data.errors?.theme?.[0] || data.message || 'Erreur serveur.');
                }

                projectId = data.project_id;
                startedAt = Date.now();

                document.getElementById('form-section').classList.add('hidden');
                document.getElementById('pipeline-section').classList.remove('hidden');
                document.getElementById('pipeline-theme').textContent = theme;

                startPolling();

            } catch (err) {
                errDiv.textContent = err.message;
                errDiv.classList.remove('hidden');
                btn.disabled = false;
                btnText.textContent = 'Generer le film';
            }
        });

        function startTimer() {
            var el = document.getElementById('elapsed-time');
            if (!el) return;
            timerInterval = setInterval(function() {
                var s = Math.floor((Date.now() - startedAt) / 1000);
                var m = Math.floor(s / 60);
                el.textContent = m > 0 ? m + 'm ' + String(s % 60).padStart(2, '0') + 's' : s + 's';
            }, 1000);
        }

        function startPolling() {
            clearInterval(pollTimer);
            clearInterval(timerInterval);
            pollFailureCount = 0;
            startTimer();
            poll();
            pollTimer = setInterval(poll, POLL_INTERVAL);
        }

        async function poll() {
            try {
                var res = await fetch('{{ url("/video/status") }}/' + projectId, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) {
                    pollFailureCount++;
                    if (pollFailureCount >= MAX_POLL_FAILURES) {
                        clearInterval(pollTimer);
                        clearInterval(timerInterval);
                        showPipelineError('Connexion au serveur instable. Rechargez la page pour reprendre le suivi.');
                    }
                    return;
                }

                var data = await res.json();
                pollFailureCount = 0;
                updatePipeline(data);

                if (data.status === 'done') {
                    clearInterval(pollTimer);
                    clearInterval(timerInterval);
                    setTimeout(function() { window.location.href = '{{ url("/video") }}/' + projectId; }, 1000);
                } else if (data.status === 'error') {
                    clearInterval(pollTimer);
                    clearInterval(timerInterval);
                    showPipelineError(data.error_message || 'Erreur inconnue dans le pipeline.');
                }
            } catch (err) {
                pollFailureCount++;
                if (pollFailureCount >= MAX_POLL_FAILURES) {
                    clearInterval(pollTimer);
                    clearInterval(timerInterval);
                    showPipelineError('Le suivi a ete interrompu. Rechargez la page pour verifier l\'avancement.');
                }
            }
        }

        function updatePipeline(data) {
            var status = data.status;
            var currentStep = data.current_step || 0;

            var stepMessages = [
                'Initialisation du pipeline...',
                'L\'IA genere l\'histoire...',
                'Creation des scenes illustrees...',
                'Generation des illustrations...',
                'Synthese vocale (ElevenLabs)...',
                'Film anime pret ! Redirection...',
            ];

            var msgEl = document.getElementById('status-message');
            if (msgEl) {
                if (status === 'done') msgEl.textContent = 'Film pret ! Redirection...';
                else if (status === 'error') msgEl.textContent = 'Erreur dans le pipeline.';
                else msgEl.textContent = stepMessages[currentStep] || stepMessages[0];
            }

            for (var s = 1; s <= 5; s++) {
                var el = document.querySelector('.step-item[data-step="' + s + '"]');
                if (!el) continue;

                var iconEl = el.querySelector('.step-icon');
                var numEl = el.querySelector('.step-num');
                var spinnerEl = el.querySelector('.step-spinner');
                var checkEl = el.querySelector('.step-check');
                var lineEl = el.querySelector('.step-line');

                iconEl.classList.remove('step-waiting', 'step-active', 'step-done', 'step-error', 'step-active-ring');

                if (status === 'done' || s < currentStep) {
                    iconEl.classList.add('step-done');
                    numEl.classList.add('hidden');
                    spinnerEl.classList.add('hidden');
                    checkEl.classList.remove('hidden');
                    iconEl.style.borderColor = '#34d399';
                    if (lineEl) { lineEl.classList.add('step-line-done'); lineEl.classList.remove('step-line-active'); }
                } else if (s === currentStep && status === 'processing') {
                    iconEl.classList.add('step-active', 'step-active-ring');
                    numEl.classList.add('hidden');
                    spinnerEl.classList.remove('hidden');
                    checkEl.classList.add('hidden');
                    iconEl.style.borderColor = '#a78bfa';
                    if (lineEl) { lineEl.classList.add('step-line-active'); lineEl.classList.remove('step-line-done'); }
                } else if (status === 'error' && s === currentStep) {
                    iconEl.classList.add('step-error');
                    numEl.classList.remove('hidden');
                    spinnerEl.classList.add('hidden');
                    checkEl.classList.add('hidden');
                    iconEl.style.borderColor = '#f87171';
                } else {
                    iconEl.classList.add('step-waiting');
                    numEl.classList.remove('hidden');
                    spinnerEl.classList.add('hidden');
                    checkEl.classList.add('hidden');
                    iconEl.style.borderColor = '';
                }
            }
        }

        function showPipelineError(message) {
            document.getElementById('pipeline-error-msg').textContent = message;
            document.getElementById('pipeline-error').classList.remove('hidden');
            document.getElementById('status-message').classList.add('hidden');
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(pollTimer);
            } else if (projectId) {
                clearInterval(pollTimer);
                poll();
                pollTimer = setInterval(poll, POLL_INTERVAL);
            }
        });
    })();
    </script>
</body>
</html>