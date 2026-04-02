# FREE_API_SETUP.md — APIs gratuites pour tester le pipeline complet

Ce guide explique comment obtenir toutes les clés API **gratuitement** (ou presque)
pour faire tourner le pipeline de bout en bout.

---

## Vue d'ensemble du pipeline

```
Thème entré → [LLM] Histoire + script → [TTS] Voix off → [Video AI] 12 clips → [FFmpeg] Vidéo finale
```

| Étape           | Service recommandé (gratuit) | Alternative payante          |
|-----------------|------------------------------|------------------------------|
| Histoire/script | **Groq** (Llama 3.3, free)   | Anthropic Claude Haiku ~$0.001/vidéo |
| Voix off        | **ElevenLabs** free tier     | OpenAI TTS ~$0.015/vidéo     |
| Clips vidéo     | **Replicate** (crédits)      | Runway ML free 125 crédits   |
| Orchestration   | **N8N Cloud** free tier      | N8N self-hosted (Docker)     |
| Hébergement app | **Railway** free tier        | Render, Fly.io               |

---

## Étape 1 — LLM pour l'histoire (choisir A ou B)

### Option A — Groq (100% gratuit, le plus rapide)

1. Aller sur https://console.groq.com
2. Créer un compte (email uniquement, pas de CB)
3. Menu gauche → **API Keys** → **Create API Key**
4. Copier la clé → la mettre dans `.env` :
   ```
   GROQ_API_KEY=gsk_...
   GROQ_MODEL=llama-3.3-70b-versatile
   ```
5. Limites gratuites : 14 400 req/jour, 6 000 tokens/min → largement suffisant

### Option B — Anthropic Claude (qualité supérieure, ~$0.001/vidéo)

1. Aller sur https://console.anthropic.com
2. Créer un compte → **$5 de crédit offerts** (≈ 5 000 vidéos avec Claude Haiku)
3. Menu haut → **API Keys** → **Create Key**
4. Copier la clé → la mettre dans `.env` :
   ```
   ANTHROPIC_API_KEY=sk-ant-...
   ```

> Dans le workflow N8N, le nœud "Claude" utilise le modèle `claude-haiku-20240307`.
> Pour Groq, remplacer ce nœud par un HTTP Request vers `https://api.groq.com/openai/v1/chat/completions`
> avec header `Authorization: Bearer {{ $env.GROQ_API_KEY }}`.

---

## Étape 2 — Voix off / Text-to-Speech

### ElevenLabs (plan gratuit : 10 000 chars/mois ≈ 6-8 vidéos)

1. Aller sur https://elevenlabs.io
2. Créer un compte (email + mot de passe)
3. En haut à droite → **Profile** → **API Key** → copier
4. Choisir une voix :
   - Aller sur **Voice Library** → écouter → noter l'ID dans l'URL
   - ID par défaut pré-configuré : `EXAVITQu4vr4xnSDxMaL` (voix "Sarah", neutre, claire)
5. Mettre dans `.env` :
   ```
   ELEVENLABS_API_KEY=...
   ELEVENLABS_VOICE_ID=EXAVITQu4vr4xnSDxMaL
   ```

---

## Étape 3 — Génération des clips vidéo (choisir A ou B)

### Option A — Replicate (crédits offerts à l'inscription ≈ $1-2)

1. Aller sur https://replicate.com
2. Créer un compte GitHub ou email
3. Menu → **Account** → **API Tokens** → **Create token**
4. Modèle recommandé : `minimax/video-01` (carte 5s/clip, ~$0.025/clip)
5. Pour 12 scènes × $0.025 = ~$0.30/vidéo complète
6. Mettre dans `.env` :
   ```
   REPLICATE_API_TOKEN=r8_...
   REPLICATE_VIDEO_MODEL=minimax/video-01
   ```

> Dans le workflow N8N, remplacer le nœud "Runway" par un HTTP Request :
> - URL : `https://api.replicate.com/v1/models/minimax/video-01/predictions`
> - Header : `Authorization: Bearer {{ $env.REPLICATE_API_TOKEN }}`
> - Body : `{"input": {"prompt": "{{ $json.visual_description }}"}}`

### Option B — Runway ML (125 crédits free = ~8-10 clips gratuits)

1. Aller sur https://runwayml.com
2. Créer un compte → **$10 de crédits offerts** à la vérification email
3. Menu → **Settings** → **API** → **Create API Key**
4. Mettre dans `.env` :
   ```
   RUNWAY_API_KEY=key_...
   ```

---

## Étape 4 — Orchestration N8N

### N8N Cloud (plan gratuit : 5 workflows actifs, 2 500 exéc/mois)

1. Aller sur https://app.n8n.cloud
2. Créer un compte → démarrer le plan **gratuit**
3. Dans N8N → **Settings** → **n8n API** → noter l'URL de votre instance
4. Importer le workflow :
   - Menu hamburger → **Workflows** → **Import from file**
   - Sélectionner `n8n_workflow.json` à la racine du projet
5. Dans le workflow, mettre à jour les credentials :
   - Nœud **Anthropic** (ou Groq) → ajouter votre clé API
   - Nœud **ElevenLabs** → ajouter votre clé API
   - Nœud **Runway/Replicate** → ajouter votre clé API
6. Activer le workflow → copier l'URL du webhook de déclenchement
7. Mettre dans `.env` :
   ```
   N8N_WEBHOOK_URL=https://votre-instance.app.n8n.cloud/webhook/video-pipeline
   N8N_WEBHOOK_SECRET=un-secret-long-et-aleatoire
   ```

---

## Étape 5 — Déploiement sur Railway

### Prérequis
- Compte GitHub (gratuit)
- Compte Railway (gratuit) : https://railway.app

### Procédure

1. **Pousser le code sur GitHub**
   ```bash
   git init
   git add .
   git commit -m "Initial commit — AI Kids Video Generator"
   git remote add origin https://github.com/TON_USER/ai-kids-video-generator.git
   git push -u origin main
   ```

2. **Créer le projet sur Railway**
   - Aller sur https://railway.app → **New Project**
   - Choisir **Deploy from GitHub repo** → sélectionner le repo
   - Railway détecte automatiquement Laravel via `composer.json`

3. **Ajouter le plugin MySQL**
   - Dans Railway → **+ New** → **Database** → **MySQL**
   - Railway injecte automatiquement `MYSQL_URL`, `MYSQL_HOST`, etc.

4. **Configurer les variables d'environnement**
   Dans Railway → onglet **Variables** → ajouter toutes les clés du `.env.example` :
   ```
   APP_KEY           → générer avec : php artisan key:generate --show
   APP_URL           → l'URL Railway (ex: https://ai-kids-xxx.up.railway.app)
   DB_HOST           → valeur auto-injectée par le plugin MySQL
   DB_DATABASE       → valeur auto-injectée
   DB_USERNAME       → valeur auto-injectée
   DB_PASSWORD       → valeur auto-injectée
   ANTHROPIC_API_KEY → votre clé
   ELEVENLABS_API_KEY→ votre clé
   RUNWAY_API_KEY    → votre clé (ou REPLICATE_API_TOKEN)
   N8N_WEBHOOK_URL   → l'URL du webhook N8N
   N8N_WEBHOOK_SECRET→ le secret partagé
   ```

5. **Premier déploiement**
   - Railway lance automatiquement `composer install` puis `php artisan migrate --force`
   - L'app est accessible sous `https://monapp.up.railway.app`
   - Partager cette URL avec la personne qui doit tester

### Plan Railway gratuit
- 500h de compute/mois (≈ 21 jours en continu)
- 1 Go de RAM, 1 vCPU
- Plugin MySQL inclus
- Suffisant pour une démonstration

---

## Récapitulatif des coûts estimés pour 10 vidéos de test

| Service       | Coût estimé       | Notes                                    |
|---------------|-------------------|------------------------------------------|
| Groq (LLM)    | **$0**            | Entièrement gratuit                      |
| ElevenLabs    | **$0**            | 10k chars gratuits → 10-12 vidéos        |
| Replicate     | **~$3**           | 12 clips × $0.025 × 10 vidéos           |
| Railway MySQL | **$0**            | Plugin gratuit inclus                    |
| Railway app   | **$0**            | 500h/mois de compute gratuit             |
| **TOTAL**     | **~$3**           | Pour 10 vidéos complètes                 |

> Avec les crédits offerts par Replicate à l'inscription ($1-2), les premières vidéos
> sont entièrement **gratuites**.
