// Script to generate the n8n workflow JSON
// Run: node _generate_workflow.js
// v10 — Simplified: N8N does Groq story only, Laravel handles Replicate + ElevenLabs

// ─── Code: Build Groq Request ───
const buildGroqCode = `
var webhookData = $('Webhook Trigger').first().json.body;
var theme = webhookData.theme;
var project_id = webhookData.project_id;

if (!project_id || !theme) {
  throw new Error('Missing project_id or theme from webhook');
}

var groqBody = {
  model: 'llama-3.3-70b-versatile',
  max_tokens: 8192,
  temperature: 0.7,
  response_format: { type: 'json_object' },
  messages: [
    {
      role: 'system',
      content: 'Tu es un auteur professionnel de videos educatives pour enfants de 6 a 10 ans. Tu ecris des scripts video de 400 a 500 mots. Tu reponds UNIQUEMENT en JSON valide. Pas de texte avant ou apres le JSON. Pas de markdown. Pas de code fences.'
    },
    {
      role: 'user',
      content: 'Ecris un script video educatif pour enfants sur le theme : "' + theme + '".\\n\\nRegles strictes :\\n- Le script complet (somme de toutes les narrations) doit faire entre 400 et 500 mots\\n- Decoupe en exactement 12 scenes\\n- Chaque scene dure entre 5 et 20 secondes\\n- Les descriptions visuelles sont en ANGLAIS (pour le generateur video)\\n- Les narrations sont en FRANCAIS (pour les enfants)\\n- Chaque description visuelle commence par "A colorful cartoon" ou "A bright animated" ou similaire\\n- La duree totale des scenes doit etre entre 120 et 180 secondes\\n\\nReponds avec ce JSON exact :\\n{\\n  "story": "resume complet de l histoire en francais (50-80 mots)",\\n  "moral": "la morale de l histoire",\\n  "scenes": [\\n    { "scene_number": 1, "visual_description": "A colorful cartoon ...", "narration": "texte narration francais 30-45 mots", "duration_seconds": 12 },\\n    ... (12 scenes au total)\\n  ]\\n}'
    }
  ]
};

return [{ json: { project_id: project_id, theme: theme, groqBody: groqBody } }];
`.trim();

// ─── Code: Parse Story (handles Buffer responses) ───
const parseStoryCode = `
var project_id = $('Build Groq Request').first().json.project_id;
var theme = $('Build Groq Request').first().json.theme;
var input = $input.first().json;

function findBufferBytes(obj, depth) {
  if (!obj || typeof obj !== 'object' || depth > 6) return null;
  if (obj.type === 'Buffer' && Array.isArray(obj.data)) return obj.data;
  var keys = Object.keys(obj);
  for (var i = 0; i < keys.length; i++) {
    var found = findBufferBytes(obj[keys[i]], depth + 1);
    if (found) return found;
  }
  return null;
}

var content = '';
if (input.choices && input.choices[0] && input.choices[0].message) {
  content = input.choices[0].message.content;
} else if (typeof input.data === 'string' && input.data.length > 10) {
  try {
    var g1 = JSON.parse(input.data);
    if (g1.choices) content = g1.choices[0].message.content;
    else content = input.data;
  } catch(e1) { content = input.data; }
} else {
  var bytes = findBufferBytes(input, 0);
  if (bytes && bytes.length > 0) {
    var rawStr = Buffer.from(bytes).toString('utf-8');
    try {
      var g2 = JSON.parse(rawStr);
      if (g2.choices && g2.choices[0]) content = g2.choices[0].message.content;
      else content = rawStr;
    } catch(e2) { content = rawStr; }
  }
}

if (!content || content.length < 5) {
  throw new Error('Cannot extract Groq content. Keys: ' + Object.keys(input).join(', '));
}

var clean = content.trim();
var fenceIdx = clean.indexOf('\`\`\`');
if (fenceIdx >= 0) {
  var lines = clean.split('\\n');
  if (lines[0].trim().indexOf('\`\`\`') === 0) lines.shift();
  if (lines.length > 0 && lines[lines.length - 1].trim() === '\`\`\`') lines.pop();
  clean = lines.join('\\n').trim();
}

var parsed;
try { parsed = JSON.parse(clean); }
catch(e3) { throw new Error('Story JSON parse error: ' + e3.message + ' | Start: ' + clean.substring(0, 300)); }

if (!parsed.scenes || !Array.isArray(parsed.scenes) || parsed.scenes.length === 0) {
  throw new Error('No scenes array. Keys: ' + Object.keys(parsed).join(', '));
}

var scenes = [];
for (var s = 0; s < parsed.scenes.length; s++) {
  var sc = parsed.scenes[s];
  scenes.push({
    scene_number: sc.scene_number || (s + 1),
    visual_description: sc.visual_description || 'A colorful cartoon scene',
    narration: sc.narration || '',
    duration_seconds: Math.max(5, Math.min(20, sc.duration_seconds || 10))
  });
}

return [{ json: { project_id: project_id, theme: theme, story: parsed.story || '', moral: parsed.moral || '', scenes: scenes } }];
`.trim();

// ─── Code: Prepare Callback (format scenes for Laravel — no video URLs) ───
const prepareCallbackCode = `
var storyData = $('Parse Story').first().json;

var scenes = [];
for (var s = 0; s < storyData.scenes.length; s++) {
  var sc = storyData.scenes[s];
  scenes.push({
    scene_number: sc.scene_number,
    visual_description: sc.visual_description,
    narration: sc.narration,
    duration_seconds: sc.duration_seconds,
    video_url: ''
  });
}

var callbackBody = JSON.stringify({
  project_id: storyData.project_id,
  video_url: 'pending',
  story_text: storyData.story,
  scenes_json: scenes
});

return [{ json: { callbackBody: callbackBody, project_id: storyData.project_id } }];
`.trim();

// ─── Helper: Notify Step node ───
function makeNotifyNode(id, name, stepNum, position, projectIdExpr) {
  return {
    parameters: {
      method: "POST",
      url: "={{ $vars.LARAVEL_URL }}/api/n8n/step",
      sendHeaders: true,
      headerParameters: {
        parameters: [
          { name: "Content-Type", value: "application/json" },
          { name: "Accept", value: "application/json" },
          { name: "X-N8N-Secret", value: "={{ $vars.N8N_WEBHOOK_SECRET }}" }
        ]
      },
      sendBody: true,
      contentType: "json",
      bodyParameters: {
        parameters: [
          { name: "project_id", value: projectIdExpr },
          { name: "step", value: String(stepNum) }
        ]
      },
      options: { timeout: 10000 }
    },
    id: id,
    name: name,
    type: "n8n-nodes-base.httpRequest",
    typeVersion: 4.2,
    position: position,
    onError: "continueRegularOutput"
  };
}

// ─── Build the workflow ───
const workflow = {
  name: "AI Kids Video Generator v10",
  nodes: [
    // 1. Webhook Trigger
    {
      parameters: {
        httpMethod: "POST",
        path: "video-pipeline",
        responseMode: "lastNode",
        options: {}
      },
      id: "webhook-trigger",
      name: "Webhook Trigger",
      type: "n8n-nodes-base.webhook",
      typeVersion: 2,
      position: [260, 400],
      webhookId: "video-pipeline"
    },

    // 2. Notify Step 1 (story generation starting)
    makeNotifyNode("step1", "Notify Step 1", 1, [480, 400], "={{ $json.body.project_id }}"),

    // 3. Build Groq Request (Code)
    {
      parameters: { jsCode: buildGroqCode },
      id: "build-groq",
      name: "Build Groq Request",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [700, 400]
    },

    // 4. Call Groq API (HTTP Request)
    {
      parameters: {
        method: "POST",
        url: "https://api.groq.com/openai/v1/chat/completions",
        sendHeaders: true,
        headerParameters: {
          parameters: [
            { name: "Authorization", value: "={{ 'Bearer ' + $vars.GROQ_API_KEY }}" },
            { name: "Content-Type", value: "application/json" }
          ]
        },
        sendBody: true,
        contentType: "raw",
        rawContentType: "application/json",
        body: "={{ JSON.stringify($json.groqBody) }}",
        options: { timeout: 60000 }
      },
      id: "call-groq",
      name: "Call Groq API",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [920, 400]
    },

    // 5. Parse Story (Code)
    {
      parameters: { jsCode: parseStoryCode },
      id: "parse-story",
      name: "Parse Story",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1140, 400]
    },

    // 6. Notify Step 2 (scenes ready)
    makeNotifyNode("step2", "Notify Step 2", 2, [1360, 400], "={{ $json.project_id }}"),

    // 7. Prepare Callback (Code — format data for Laravel)
    {
      parameters: { jsCode: prepareCallbackCode },
      id: "prepare-callback",
      name: "Prepare Callback",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1580, 400]
    },

    // 8. Send Callback to Laravel
    {
      parameters: {
        method: "POST",
        url: "={{ $vars.LARAVEL_URL }}/api/n8n/callback",
        sendHeaders: true,
        headerParameters: {
          parameters: [
            { name: "Content-Type", value: "application/json" },
            { name: "Accept", value: "application/json" },
            { name: "X-N8N-Secret", value: "={{ $vars.N8N_WEBHOOK_SECRET }}" }
          ]
        },
        sendBody: true,
        contentType: "raw",
        rawContentType: "application/json",
        body: "={{ $json.callbackBody }}",
        options: { timeout: 120000 }
      },
      id: "send-callback",
      name: "Send Callback",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [1800, 400]
    }
  ],
  connections: {
    "Webhook Trigger":   { main: [[{ node: "Notify Step 1",     type: "main", index: 0 }]] },
    "Notify Step 1":     { main: [[{ node: "Build Groq Request", type: "main", index: 0 }]] },
    "Build Groq Request":{ main: [[{ node: "Call Groq API",      type: "main", index: 0 }]] },
    "Call Groq API":     { main: [[{ node: "Parse Story",        type: "main", index: 0 }]] },
    "Parse Story":       { main: [[{ node: "Notify Step 2",      type: "main", index: 0 }]] },
    "Notify Step 2":     { main: [[{ node: "Prepare Callback",   type: "main", index: 0 }]] },
    "Prepare Callback":  { main: [[{ node: "Send Callback",      type: "main", index: 0 }]] }
  },
  active: false,
  settings: { executionOrder: "v1" }
};

require('fs').writeFileSync(
  'n8n_workflow.json',
  JSON.stringify(workflow, null, 2),
  'utf-8'
);

console.log('Workflow v10 generated successfully!');
console.log('Nodes:', workflow.nodes.length);
console.log('Flow: Webhook → Groq → Parse → Callback (Laravel handles Replicate)');
Object.keys(workflow.connections).forEach(k => {
  const targets = workflow.connections[k].main[0].map(c => c.node);
  console.log(' ', k, '→', targets.join(', '));
});
