# 🎬 AI Kids Video Generator

Application web Laravel 11 qui génère automatiquement une **vidéo animée de 3 minutes** pour enfants (8 ans) à partir d'un simple thème saisi par l'utilisateur. Le pipeline de génération est orchestré par **N8N** qui enchaîne Claude, ElevenLabs et Runway ML.

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
│  │  • Lecteur vidéo    │    │  GET  /video/{id}          │  │
│  │  • Polling AJAX 3s  │    │  POST /api/n8n/callback    │  │
│  └─────────────────────┘    │  POST /api/n8n/error       │  │
│                             │  POST /api/n8n/step        │  │
│          MySQL              └────────────┬───────────────┘  │
│  ┌─────────────────────┐                 │ webhook          │
│  │  video_projects     │                 ▼                  │
│  │  ─────────────────  │    ┌────────────────────────────┐  │
│  │  id, theme, status  │    │           N8N              │  │
│  │  story_text         │    │  ─────────────────────     │  │
│  │  scenes_json        │    │  1. Claude  → histoire     │  │
│  │  video_url          │    │  2. Parse   → scènes JSON  │  │
│  │  current_step       │    │  3. Split   → par scène    │  │
│  └─────────────────────┘    │  4. ElevenLabs → MP3       │  │
│                             │  5. Runway  → clip vidéo   │  │
│                             │  6. Poll loop (30s retry)  │  │
│                             │  7. FFmpeg  → assemblage   │  │
│                             │  8. Callback → Laravel     │  │
│                             └────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## Prérequis

| Outil             | Version minimum | Rôle                          |
|-------------------|-----------------|-------------------------------|
| PHP               | 8.2+            | Runtime Laravel               |
| Composer          | 2.x             | Gestionnaire dépendances PHP  |
| MySQL             | 8.0+            | Base de données               |
| Node.js           | 18+             | Build assets (optionnel CDN)  |
| Python            | 3.10+           | Script assemblage FFmpeg      |
| FFmpeg            | 6.x             | Encodage et concaténation     |
| N8N               | 1.x             | Orchestration workflow IA     |
| pip packages      | —               | `requests` (Python)           |

---

## Installation pas à pas

### 1. Cloner / créer le projet Laravel

```bash
# Si vous partez de zéro
composer create-project laravel/laravel video-kids-generator "^11.0"
cd video-kids-generator

# Copiez tous les fichiers de ce projet dans le dossier Laravel créé
```

### 2. Configurer l'environnement

```bash
# Copier le fichier d'exemple
cp .env.example .env

# Générer la clé d'application
php artisan key:generate
```

Éditez `.env` et remplissez au minimum :

```
DB_DATABASE=video_kids_generator
DB_USERNAME=root
DB_PASSWORD=votre_mot_de_passe

ANTHROPIC_API_KEY=sk-ant-...
ELEVENLABS_API_KEY=...
ELEVENLABS_VOICE_ID=EXAVITQu4vr4xnSDxMaL
RUNWAY_API_KEY=key_...
N8N_WEBHOOK_URL=https://votre-n8n/webhook/video-pipeline
N8N_WEBHOOK_SECRET=un-secret-aleatoire-long
```

### 3. Créer la base de données et migrer

```bash
# Créer la base MySQL (si elle n'existe pas)
mysql -u root -p -e "CREATE DATABASE video_kids_generator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Lancer les migrations
php artisan migrate
```

### 4. Configurer le stockage vidéo

```bash
# Crée le lien symbolique public/storage → storage/app/public
php artisan storage:link

# Crée le dossier de stockage vidéo
mkdir -p storage/app/public/videos
chmod 775 storage/app/public/videos
```

> Les vidéos seront accessibles via `/storage/videos/{id}.mp4`

### 5. Installer Python et FFmpeg

**Ubuntu/Debian :**
```bash
sudo apt install python3 python3-pip ffmpeg
pip3 install requests

# Copier le script Python au bon endroit
sudo mkdir -p /scripts
sudo cp scripts/assemble_video.py /scripts/
sudo chmod +x /scripts/assemble_video.py
```

**macOS :**
```bash
brew install python ffmpeg
pip3 install requests
sudo mkdir -p /scripts && sudo cp scripts/assemble_video.py /scripts/
```

**Windows (développement local) :**
```powershell
# Installer FFmpeg : https://ffmpeg.org/download.html (ajouter au PATH)
pip install requests
# Adapter le chemin dans le workflow N8N (nœud "Run FFmpeg Assembly")
```

### 6. Lancer le serveur Laravel

```bash
# Développement
php artisan serve

# L'application est accessible sur http://localhost:8000
```

---

## Configuration des APIs

### Anthropic Claude
1. Aller sur [console.anthropic.com](https://console.anthropic.com/)
2. Créer un compte / se connecter
3. Aller dans **API Keys** → Créer une clé
4. Copier la clé dans `ANTHROPIC_API_KEY`

### ElevenLabs (voix off)
1. Créer un compte sur [elevenlabs.io](https://elevenlabs.io)
2. Aller dans **Profile > API Key** → Copier la clé dans `ELEVENLABS_API_KEY`
3. Pour choisir une voix : aller dans **Voice Library**, noter l'ID de la voix souhaitée
4. La voix par défaut `EXAVITQu4vr4xnSDxMaL` est "Rachel" (anglais/multilingue)
5. Pour du français : chercher une voix avec le tag "French" dans la librairie

### Runway ML (génération vidéo)
1. Créer un compte sur [app.runwayml.com](https://app.runwayml.com)
2. Aller dans **Settings > API Keys** → Créer une clé
3. Copier dans `RUNWAY_API_KEY`
4. ⚠️ Runway est payant, vérifiez vos crédits avant de lancer

---

## Import et configuration du workflow N8N

### Option A — N8N Cloud

1. Aller sur [app.n8n.cloud](https://app.n8n.cloud) et créer un compte
2. Créer un nouveau workflow
3. **Menu ⋮ > Import from file** → sélectionner `n8n_workflow.json`
4. Configurer les variables d'environnement N8N :
   - Aller dans **Settings > Variables**
   - Créer les variables suivantes :

   | Variable              | Valeur                                    |
   |-----------------------|-------------------------------------------|
   | `ANTHROPIC_API_KEY`   | Votre clé Claude                          |
   | `ELEVENLABS_API_KEY`  | Votre clé ElevenLabs                      |
   | `ELEVENLABS_VOICE_ID` | `EXAVITQu4vr4xnSDxMaL`                   |
   | `RUNWAY_API_KEY`      | Votre clé Runway                          |
   | `LARAVEL_URL`         | URL publique de votre app Laravel         |

5. Activer le workflow (toggle **Active**)
6. Copier l'**URL du webhook** → coller dans `N8N_WEBHOOK_URL` du `.env` Laravel

### Option B — N8N self-hosted (Docker)

```bash
docker run -d \
  --name n8n \
  -p 5678:5678 \
  -e N8N_BASIC_AUTH_ACTIVE=true \
  -e N8N_BASIC_AUTH_USER=admin \
  -e N8N_BASIC_AUTH_PASSWORD=password \
  -v ~/.n8n:/home/node/.n8n \
  n8nio/n8n

# Accéder à http://localhost:5678
# Import du workflow via l'interface
```

> **Important** : Le nœud "Run FFmpeg Assembly" utilise `python3 /scripts/assemble_video.py`.
> En mode self-hosted Docker, montez le script : `-v /votre/scripts:/scripts`

### Correction du chemin FFmpeg sur Windows

Dans le workflow N8N, nœud **"Run FFmpeg Assembly"**, adaptez la commande :
```
python C:\chemin\vers\assemble_video.py --scenes '...' --output C:\laravel\storage\app\public\videos\%project_id%.mp4
```

---

## Lancer le projet complet

```bash
# Terminal 1 — Laravel
php artisan serve

# Terminal 2 — (optionnel) Queue worker si vous passez QUEUE_CONNECTION=database
php artisan queue:work

# N8N doit être démarré et le workflow actif
```

Ouvrez [http://localhost:8000](http://localhost:8000), saisissez un thème et cliquez **Générer la vidéo**.

---

## Structure des fichiers

```
.
├── app/
│   ├── Http/Controllers/
│   │   ├── VideoController.php          # Formulaire, polling, page résultat
│   │   └── N8NCallbackController.php    # Callbacks N8N (success/error/step)
│   └── Models/
│       └── VideoProject.php             # Modèle Eloquent
│
├── config/
│   └── services.php                     # Clés N8N, Claude, ElevenLabs, Runway
│
├── database/
│   └── migrations/
│       └── 2024_01_01_..._create_video_projects_table.php
│
├── resources/views/video/
│   ├── index.blade.php                  # Formulaire + suivi pipeline temps réel
│   └── result.blade.php                 # Lecteur vidéo + scènes + téléchargement
│
├── routes/
│   ├── web.php                          # Routes UI Laravel
│   └── api.php                          # Routes callbacks N8N
│
├── scripts/
│   └── assemble_video.py                # Assemblage FFmpeg (appelé par N8N)
│
├── n8n_workflow.json                    # Workflow N8N exportable (v1.x)
├── .env.example                         # Variables d'environnement (template)
└── README.md
```

---

## Variables d'environnement — Récapitulatif

| Variable                | Requis | Description                          |
|-------------------------|--------|--------------------------------------|
| `APP_KEY`               | ✅     | Généré par `php artisan key:generate`|
| `APP_URL`               | ✅     | URL publique de l'application        |
| `DB_*`                  | ✅     | Connexion MySQL                      |
| `ANTHROPIC_API_KEY`     | ✅     | Clé API Claude (Anthropic)           |
| `ELEVENLABS_API_KEY`    | ✅     | Clé API ElevenLabs                   |
| `ELEVENLABS_VOICE_ID`   | ✅     | ID de la voix ElevenLabs             |
| `RUNWAY_API_KEY`        | ✅     | Clé API Runway ML                    |
| `N8N_WEBHOOK_URL`       | ✅     | URL complète du webhook N8N          |
| `N8N_WEBHOOK_SECRET`    | ⚠️    | Secret partagé Laravel ↔ N8N        |

---

## Notes importantes

- **Coût API** : Runway ML est payant (~$0.05 par seconde vidéo). Pour 12 scènes de 15s = 180s = ~$9 par vidéo. Runway facture par seconde générée.
- **Temps de génération** : Comptez 5 à 15 minutes par vidéo selon la charge des APIs.
- **Runway polling** : Le workflow N8N attend 30 secondes entre chaque vérification. Si la vidéo n'est pas prête, il reboucle automatiquement.
- **Stockage** : Les vidéos sont stockées dans `storage/app/public/videos/`. Pensez à la capacité disque en production.
- **Production** : Configurez un vrai drive (S3, etc.) via `FILESYSTEM_DISK=s3` et adaptez la génération des URLs.

---

*Projet réalisé dans le cadre d'un stage — Stack : Laravel 11 · N8N · Claude claude-sonnet-4-5 · ElevenLabs · Runway ML · FFmpeg*
