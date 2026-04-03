# FREE_API_SETUP.md — APIs gratuites pour tester le pipeline complet

Ce guide explique comment obtenir toutes les clés API **gratuitement**
pour faire tourner le pipeline de bout en bout.

---

## Vue d'ensemble du pipeline

```
Thème entré → [Groq LLM] Histoire + script → [Pollinations.ai] Images → [ElevenLabs] Voix → Film animé
```

| Étape            | Service utilisé                  | Coût            |
|------------------|----------------------------------|-----------------|
| Histoire/script  | **Groq** (Llama 3.3, free)       | Gratuit         |
| Images           | **Pollinations.ai** (FLUX)       | Gratuit (illimité, pas de clé) |
| Voix off         | **ElevenLabs** free tier         | Gratuit (10k chars/mois) |
| Orchestration    | **N8N Cloud** free tier          | Gratuit (2500 exéc/mois) |
| Hébergement app  | **Railway** free tier            | Gratuit (500h/mois) |

---

## Étape 1 — LLM pour l'histoire

### Groq (100% gratuit, le plus rapide)

1. Aller sur https://console.groq.com
2. Créer un compte (email uniquement, pas de CB)
3. Menu gauche → **API Keys** → **Create API Key**
4. Copier la clé → la mettre dans `.env` :
   ```
   GROQ_API_KEY=gsk_...
   GROQ_MODEL=llama-3.3-70b-versatile
   ```
5. Limites gratuites : 14 400 req/jour, 12 000 tokens/min
6. Le workflow N8N inclut un retry automatique (3 tentatives, 20s d'attente) pour gérer le rate limit

---

## Étape 2 — Génération des images

### Pollinations.ai (100% gratuit, illimité, AUCUNE clé API)

Pollinations.ai est un service de génération d'images basé sur le modèle FLUX.
**Aucune inscription, aucune clé API, aucune limite.**

Les images sont générées via une simple URL :
```
https://image.pollinations.ai/prompt/A%20colorful%20cartoon%20robot?width=1280&height=720&nologo=true&seed=42
```

Le système génère automatiquement les URLs pour chaque scène. Rien à configurer.

---

## Étape 3 — Voix off / Text-to-Speech

### ElevenLabs (plan gratuit : 10 000 chars/mois ≈ 6-8 vidéos)

1. Aller sur https://elevenlabs.io
2. Créer un compte (email + mot de passe)
3. En haut à droite → **Profile** → **API Key** → copier
4. Le projet utilise 4 voix dynamiques sélectionnées automatiquement par l'IA :
   - **Bella** (narratrice) — `EXAVITQu4vr4xnSDxMaL`
   - **Antoni** (narrateur) — `ErXwobaYiN019PkySvjV`
   - **Gigi** (enfant fille) — `jBpfuIE2acCO8z3wKNLl`
   - **Sam** (enfant garçon) — `yoZ06aMxZJJ28mfd3POQ`
5. Mettre dans `.env` :
   ```
   ELEVENLABS_API_KEY=...
   ELEVENLABS_VOICE_ID=EXAVITQu4vr4xnSDxMaL
   ```
6. Si le quota est atteint, le système bascule automatiquement sur la synthèse vocale du navigateur.

---

## Étape 4 — Orchestration N8N

### N8N Cloud (plan gratuit : 5 workflows actifs, 2 500 exéc/mois)

1. Aller sur https://app.n8n.cloud et créer un compte
2. Créer un nouveau workflow
3. **Menu ⋮ > Import from URL** → coller :
   ```
   https://raw.githubusercontent.com/Chaft92/offre-stage-video-enfants/master/n8n_workflow.json
   ```
4. Configurer les variables N8N :
   - Aller dans **Settings > Variables**
   - Créer les variables suivantes :

   | Variable              | Valeur                                    |
   |-----------------------|-------------------------------------------|
   | `GROQ_API_KEY`        | Votre clé Groq                            |
   | `LARAVEL_URL`         | URL publique de votre app Railway         |
   | `N8N_WEBHOOK_SECRET`  | Le même secret que dans le `.env` Laravel |

5. Activer le workflow (toggle **Active**)
6. Copier l'**URL du webhook** → coller dans `N8N_WEBHOOK_URL` du `.env` Laravel

---

## Étape 5 — Déploiement sur Railway

### Prérequis
- Compte GitHub (gratuit)
- Compte Railway (gratuit) : https://railway.app

### Procédure

1. **Pousser le code sur GitHub** (déjà fait)
2. **Créer le projet sur Railway**
   - Aller sur https://railway.app → **New Project**
   - Choisir **Deploy from GitHub repo** → sélectionner le repo
3. **Ajouter le plugin MySQL**
   - Dans Railway → **+ New** → **Database** → **MySQL**
4. **Configurer les variables d'environnement**
   Dans Railway → onglet **Variables** → ajouter :
   ```
   APP_KEY           → php artisan key:generate --show
   APP_URL           → l'URL Railway
   GROQ_API_KEY      → votre clé Groq
   ELEVENLABS_API_KEY→ votre clé ElevenLabs
   ELEVENLABS_VOICE_ID→ EXAVITQu4vr4xnSDxMaL
   N8N_WEBHOOK_URL   → l'URL du webhook N8N
   N8N_WEBHOOK_SECRET→ le secret partagé
   ```

### Plan Railway gratuit
- 500h de compute/mois (≈ 21 jours en continu)
- 1 Go de RAM, 1 vCPU
- Plugin MySQL inclus

---

## Récapitulatif des coûts pour tester

| Service          | Coût estimé | Notes                                    |
|------------------|-------------|------------------------------------------|
| Groq (LLM)      | **$0**      | Entièrement gratuit                      |
| Pollinations.ai  | **$0**      | Entièrement gratuit, illimité            |
| ElevenLabs       | **$0**      | 10k chars gratuits → ~8 vidéos/mois      |
| Railway + MySQL  | **$0**      | 500h/mois de compute gratuit             |
| N8N Cloud        | **$0**      | 2500 exécutions/mois gratuites           |
| **TOTAL**        | **$0**      | Entièrement gratuit pour la démo         |
