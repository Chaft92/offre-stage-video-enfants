# 🎬 AI Kids Video Generator

Application web Laravel 11 qui génère automatiquement **un film animé illustré** pour enfants à partir d'un simple thème. Le pipeline est orchestré par **N8N** qui utilise **Groq** (Llama 3.3) pour le scénario, **Pollinations.ai** pour les illustrations et **ElevenLabs** pour la narration vocale dynamique.

**100% gratuit** : toutes les APIs utilisées sont gratuites (aucune carte bancaire requise).

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     LARAVEL 11 (Backend + Frontend)         │
│                                                             │
│  ┌─────────────────────┐    ┌────────────────────────────┐  │
│  │  Interface Blade    │    │     API REST               │  │
│  │  ─────────────────  │    │  ─────────────────────     │  │
│  │  • Formulaire thème │    │  POST /video/generate      │  │
│  │  • Pipeline visuel  │    │  GET  /video/status/{id}   │  │
│  │  • Lecteur cinéma   │    │  GET  /video/{id}          │  │
│  │  • Polling AJAX 3s  │    │  POST /api/n8n/callback    │  │
│  └─────────────────────┘    │  POST /api/n8n/error       │  │
│                             │  POST /api/n8n/step        │  │
│          MySQL              └────────────┬───────────────┘  │
│  ┌─────────────────────┐                 │ webhook          │
│  │  video_projects     │                 ▼                  │
│  │  ─────────────────  │    ┌────────────────────────────┐  │
│  │  id, theme, status  │    │           N8N (8 nodes)    │  │
│  │  story_text, moral  │    │  ─────────────────────     │  │
│  │  scenes_json        │    │  1. Groq    → histoire     │  │
│  │  current_step       │    │  2. Parse   → scènes JSON  │  │
│  └─────────────────────┘    │  3. Callback → Laravel     │  │
│                             │                            │  │
│  Laravel génère ensuite :   │  Retry auto (3×, 20s)      │  │
│  • URLs Pollinations.ai     │  pour rate-limit Groq      │  │
│  • TTS ElevenLabs (4 voix)  └────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Stack technique

| Outil             | Rôle                                     |
|-------------------|------------------------------------------|
| PHP 8.2+ / Laravel 11 | Backend + Frontend (Blade)          |
| MySQL 8.0+        | Base de données                          |
| Groq (Llama 3.3)  | Génération du scénario (gratuit)         |
| Pollinations.ai   | Génération des images (gratuit, illimité)|
| ElevenLabs        | Voix off dynamique (4 voix, gratuit)     |
| N8N Cloud         | Orchestration workflow (gratuit)         |
| Railway           | Hébergement (gratuit)                    |

---

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/Chaft92/offre-stage-video-enfants.git
cd offre-stage-video-enfants
composer install
```

### 2. Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Éditez `.env` et remplissez :

```
DB_DATABASE=video_kids_generator
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe

GROQ_API_KEY=gsk_...
ELEVENLABS_API_KEY=...
ELEVENLABS_VOICE_ID=EXAVITQu4vr4xnSDxMaL
N8N_WEBHOOK_URL=https://votre-n8n/webhook/video-pipeline
N8N_WEBHOOK_SECRET=un-secret-aleatoire-long
```

### 3. Créer la base de données et migrer

```bash
php artisan migrate
```

### 4. Lancer le serveur

```bash
php artisan serve
# → http://localhost:8000
```

---

## Configuration des APIs

> Voir [`FREE_API_SETUP.md`](FREE_API_SETUP.md) pour les instructions détaillées.

### Groq (scénario IA — gratuit)
1. [console.groq.com](https://console.groq.com) → créer un compte → **API Keys**
2. Copier la clé → `GROQ_API_KEY` dans `.env`

### Pollinations.ai (images — gratuit, aucune config)
Aucune clé API nécessaire. Les images sont générées via URL automatiquement.

### ElevenLabs (voix off — gratuit, 10k chars/mois)
1. [elevenlabs.io](https://elevenlabs.io) → créer un compte → **Profile > API Key**
2. Copier la clé → `ELEVENLABS_API_KEY` dans `.env`
3. 4 voix dynamiques sont sélectionnées automatiquement par l'IA :
   - **Bella** (narratrice) · **Antoni** (narrateur) · **Gigi** (enfant fille) · **Sam** (enfant garçon)

---

## Import du workflow N8N

1. Aller sur [app.n8n.cloud](https://app.n8n.cloud) → créer un workflow
2. **Menu ⋮ > Import from URL** → coller :
   ```
   https://raw.githubusercontent.com/Chaft92/offre-stage-video-enfants/master/n8n_workflow.json
   ```
3. **Settings > Variables** → créer :

   | Variable              | Valeur                                    |
   |-----------------------|-------------------------------------------|
   | `GROQ_API_KEY`        | Votre clé Groq                            |
   | `LARAVEL_URL`         | URL publique de votre app                 |
   | `N8N_WEBHOOK_SECRET`  | Même valeur que dans `.env` Laravel       |

4. Activer le workflow → copier l'URL webhook → `N8N_WEBHOOK_URL` dans `.env`

---

## Structure des fichiers

```
.
├── app/
│   ├── Http/Controllers/
│   │   ├── VideoController.php          # Formulaire, polling, TTS proxy, page résultat
│   │   └── N8NCallbackController.php    # Callbacks N8N + génération URLs Pollinations
│   └── Models/
│       └── VideoProject.php             # Modèle Eloquent
│
├── config/
│   └── services.php                     # Clés N8N, Groq, ElevenLabs
│
├── database/migrations/                 # Création + ajout colonne moral
│
├── resources/views/video/
│   ├── index.blade.php                  # Formulaire + suivi pipeline temps réel
│   └── result.blade.php                 # Lecteur cinéma + scènes + morale + téléchargement
│
├── routes/
│   ├── web.php                          # Routes UI
│   └── api.php                          # Routes callbacks N8N
│
├── _generate_workflow.js                # Générateur du workflow N8N (v11)
├── n8n_workflow.json                    # Workflow N8N exportable
├── FREE_API_SETUP.md                   # Guide détaillé pour obtenir les clés API
├── .env.example                         # Variables d'environnement
└── README.md
```

---

## Variables d'environnement

| Variable                | Requis | Description                          |
|-------------------------|--------|--------------------------------------|
| `APP_KEY`               | ✅     | Généré par `php artisan key:generate`|
| `APP_URL`               | ✅     | URL publique de l'application        |
| `DB_*`                  | ✅     | Connexion MySQL                      |
| `GROQ_API_KEY`          | ✅     | Clé API Groq (Llama 3.3)            |
| `ELEVENLABS_API_KEY`    | ✅     | Clé API ElevenLabs                   |
| `ELEVENLABS_VOICE_ID`   | ✅     | ID voix par défaut ElevenLabs        |
| `N8N_WEBHOOK_URL`       | ✅     | URL complète du webhook N8N          |
| `N8N_WEBHOOK_SECRET`    | ⚠️    | Secret partagé Laravel ↔ N8N        |

---

## Fonctionnalités

- **Scénario IA avancé** : introduction, développement, conclusion autour d'une morale éducative
- **10–15 scènes** de 8 à 15 secondes chacune (2–3 minutes au total)
- **Images IA** générées par Pollinations.ai (FLUX) — gratuites et illimitées
- **4 voix dynamiques** ElevenLabs sélectionnées par l'IA selon le contexte de chaque scène
- **Lecteur cinéma** avec effet Ken Burns, barre de progression, narration automatique
- **Morale affichée** en fin de film
- **Téléchargement** du pack complet (script + narrations + images) en ZIP
- **Fallback browser TTS** si le quota ElevenLabs est atteint

---

## Notes

- **Coût** : **$0** — toutes les APIs sont gratuites.
- **Temps de génération** : ~30 secondes (Groq + callback).
- **Pollinations.ai** : les premières images peuvent prendre 5-15s à charger (mises en cache ensuite).
- **ElevenLabs** : 10 000 caractères/mois gratuits. Au-delà, le navigateur prend le relais.
- **Groq rate limit** : 12 000 tokens/minute. Le workflow inclut un retry automatique (3 tentatives, 20s d'attente).

---

Fait par **Julien YILDIZ** — Rendu test de stage
