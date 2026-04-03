// Script to generate the n8n workflow JSON
// Run: node _generate_workflow.js
// v9 — 12 scenes, SplitInBatches loops for Replicate, reliable polling

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
      content: 'Ecris un script video educatif pour enfants sur le theme : \"' + theme + '\".\\n\\nRegles strictes :\\n- Le script complet (somme de toutes les narrations) doit faire entre 400 et 500 mots\\n- Decoupe en exactement 12 scenes\\n- Chaque scene dure entre 5 et 20 secondes\\n- Les descriptions visuelles sont en ANGLAIS (pour le generateur video)\\n- Les narrations sont en FRANCAIS (pour les enfants)\\n- Chaque description visuelle commence par \"A colorful cartoon\" ou \"A bright animated\" ou similaire\\n- La duree totale des scenes doit etre entre 120 et 180 secondes\\n\\nReponds avec ce JSON exact :\\n{\\n  \"story\": \"resume complet de l histoire en francais (50-80 mots)\",\\n  \"moral\": \"la morale de l histoire\",\\n  \"scenes\": [\\n    { \"scene_number\": 1, \"visual_description\": \"A colorful cartoon ...\", \"narration\": \"texte narration francais 30-45 mots\", \"duration_seconds\": 12 },\\n    ... (12 scenes au total)\\n  ]\\n}'
    }
  ]
};

return [{ json: { project_id: project_id, theme: theme, groqBody: groqBody } }];
`.trim();

// ─── Code: Parse Story (handles Buffer) ───
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

// ─── Code: Split Scenes (outputs N items for Replicate) ───
const splitScenesCode = `
var data = $('Parse Story').first().json;
var items = [];

for (var i = 0; i < data.scenes.length; i++) {
  var sc = data.scenes[i];
  items.push({
    json: {
      project_id: data.project_id,
      scene_index: i,
      scene_number: sc.scene_number,
      prompt: sc.visual_description + '. Cartoon style animation for children, bright colors, child-friendly, smooth animation, high quality, 5 seconds.'
    }
  });
}

return items;
`.trim();

// ─── Code: Collect Predictions (reads all Create Prediction outputs) ───
const collectPredictionsCode = `
var storyData = $('Parse Story').first().json;

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

function parseResp(input) {
  if (!input) return null;
  if (input.id) return input;
  if (typeof input.data === 'string') { try { return JSON.parse(input.data); } catch(e) {} }
  var bytes = findBufferBytes(input, 0);
  if (bytes && bytes.length > 0) { try { return JSON.parse(Buffer.from(bytes).toString('utf-8')); } catch(e) {} }
  return input;
}

var createItems = $('Create Prediction').all();
var predictionUrls = [];
var debugInfo = [];

for (var i = 0; i < createItems.length; i++) {
  var raw = createItems[i].json;
  var p = parseResp(raw);
  debugInfo.push('item' + i + ':id=' + (p ? p.id : 'null') + ',status=' + (p ? p.status : 'null'));
  if (p && p.id) {
    predictionUrls.push((p.urls && p.urls.get) ? p.urls.get : 'https://api.replicate.com/v1/predictions/' + p.id);
  } else {
    predictionUrls.push('');
  }
}

return [{ json: {
  project_id: storyData.project_id,
  prediction_urls: predictionUrls,
  scene_count: storyData.scenes.length,
  debug: 'Created ' + createItems.length + ' predictions: ' + debugInfo.join('; ')
} }];
`.trim();

// ─── Code: Split for Poll (output one item per prediction URL) ───
const splitForPollCode = `
var data = $('Collect Predictions').first().json;
var items = [];

for (var i = 0; i < data.prediction_urls.length; i++) {
  if (data.prediction_urls[i]) {
    items.push({
      json: {
        poll_url: data.prediction_urls[i],
        scene_index: i,
        project_id: data.project_id
      }
    });
  }
}

if (items.length === 0) {
  items.push({ json: { poll_url: 'https://api.replicate.com/v1/predictions/none', scene_index: -1, project_id: data.project_id } });
}

return items;
`.trim();

// ─── Code: Prepare Callback (extract video URLs from poll results) ───
const prepareCallbackCode = `
var storyData = $('Parse Story').first().json;

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

function parseResp(input) {
  if (!input) return null;
  if (input.status) return input;
  if (typeof input.data === 'string') { try { return JSON.parse(input.data); } catch(e) {} }
  var bytes = findBufferBytes(input, 0);
  if (bytes && bytes.length > 0) { try { return JSON.parse(Buffer.from(bytes).toString('utf-8')); } catch(e) {} }
  return input;
}

var videoUrls = {};
var pollSources = ['Poll Final', 'Poll Prediction'];

for (var src = 0; src < pollSources.length; src++) {
  try {
    var items = $(pollSources[src]).all();
    for (var i = 0; i < items.length; i++) {
      var sceneIdx = items[i].json.scene_index;
      if (sceneIdx === undefined) sceneIdx = i;
      if (videoUrls[sceneIdx]) continue;

      var p = parseResp(items[i].json);
      if (p && p.status === 'succeeded' && p.output) {
        var out = p.output;
        if (typeof out === 'string' && out.startsWith('http')) videoUrls[sceneIdx] = out;
        else if (Array.isArray(out) && out.length > 0 && typeof out[0] === 'string') videoUrls[sceneIdx] = out[0];
      }
    }
  } catch(e) {}
}

var scenes = [];
for (var s = 0; s < storyData.scenes.length; s++) {
  var sc = storyData.scenes[s];
  scenes.push({
    scene_number: sc.scene_number,
    visual_description: sc.visual_description,
    narration: sc.narration,
    duration_seconds: sc.duration_seconds,
    video_url: videoUrls[s] || ''
  });
}

var mainVideoUrl = '';
for (var k in videoUrls) {
  if (videoUrls[k]) { mainVideoUrl = videoUrls[k]; break; }
}
if (!mainVideoUrl) mainVideoUrl = 'https://demo-video-placeholder.example.com/video.mp4';

var callbackBody = JSON.stringify({
  project_id: storyData.project_id,
  video_url: mainVideoUrl,
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

// ─── Build workflow ───
const workflow = {
  name: "Video Pipeline v9 — 12 Scenes + SplitInBatches",
  nodes: [
    // 1. Webhook Trigger
    {
      parameters: {
        httpMethod: "POST",
        path: "video-pipeline",
        responseMode: "onReceived",
        options: {}
      },
      id: "webhook-trigger",
      name: "Webhook Trigger",
      type: "n8n-nodes-base.webhook",
      typeVersion: 1.1,
      position: [240, 400],
      webhookId: "wh-video-pipeline-001"
    },

    // 2. Notify Step 1
    makeNotifyNode("step1", "Notify Step 1", 1, [460, 400], "={{ $json.body.project_id }}"),

    // 3. Build Groq Request
    {
      parameters: { jsCode: buildGroqCode },
      id: "build-groq",
      name: "Build Groq Request",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [680, 400]
    },

    // 4. Call Groq API
    {
      parameters: {
        method: "POST",
        url: "https://api.groq.com/openai/v1/chat/completions",
        sendHeaders: true,
        headerParameters: {
          parameters: [
            { name: "Authorization", value: "={{ 'Bearer ' + $vars.GROQ_API_KEY }}" },
            { name: "Content-Type", value: "application/json" },
            { name: "Accept", value: "application/json" }
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
      position: [900, 400]
    },

    // 5. Parse Story
    {
      parameters: { jsCode: parseStoryCode },
      id: "parse-story",
      name: "Parse Story",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1120, 400]
    },

    // 6. Notify Step 2
    makeNotifyNode("step2", "Notify Step 2", 2, [1340, 400], "={{ $json.project_id }}"),

    // 7. Split Scenes (outputs 12 items)
    {
      parameters: { jsCode: splitScenesCode },
      id: "split-scenes",
      name: "Split Scenes",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1560, 400]
    },

    // 8. Batch Create (SplitInBatches — loops through items one by one)
    {
      parameters: {
        batchSize: 1,
        options: {}
      },
      id: "batch-create",
      name: "Batch Create",
      type: "n8n-nodes-base.splitInBatches",
      typeVersion: 3,
      position: [1780, 400]
    },

    // 9. Create Prediction (HTTP Request, processes 1 item at a time via loop)
    {
      parameters: {
        method: "POST",
        url: "https://api.replicate.com/v1/models/minimax/video-01/predictions",
        sendHeaders: true,
        headerParameters: {
          parameters: [
            { name: "Authorization", value: "={{ 'Bearer ' + $vars.REPLICATE_API_TOKEN }}" },
            { name: "Content-Type", value: "application/json" },
            { name: "Accept", value: "application/json" }
          ]
        },
        sendBody: true,
        contentType: "raw",
        rawContentType: "application/json",
        body: "={{ JSON.stringify({ input: { prompt: $json.prompt, prompt_optimizer: true } }) }}",
        options: { timeout: 30000 }
      },
      id: "create-prediction",
      name: "Create Prediction",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [2000, 600],
      onError: "continueRegularOutput"
    },

    // 10. Collect Predictions (Code — reads all Create Prediction outputs)
    {
      parameters: { jsCode: collectPredictionsCode },
      id: "collect-predictions",
      name: "Collect Predictions",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [2000, 400]
    },

    // 11. Notify Step 3
    makeNotifyNode("step3", "Notify Step 3", 3, [2220, 400], "={{ $json.project_id }}"),

    // 12. Wait 5 minutes
    {
      parameters: { amount: 5, unit: "minutes" },
      id: "wait-5min",
      name: "Wait 5min",
      type: "n8n-nodes-base.wait",
      typeVersion: 1.1,
      position: [2440, 400]
    },

    // 13. Split for Poll (outputs 12 items from prediction URLs)
    {
      parameters: { jsCode: splitForPollCode },
      id: "split-poll",
      name: "Split for Poll",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [2660, 400]
    },

    // 14. Batch Poll (SplitInBatches)
    {
      parameters: {
        batchSize: 1,
        options: {}
      },
      id: "batch-poll",
      name: "Batch Poll",
      type: "n8n-nodes-base.splitInBatches",
      typeVersion: 3,
      position: [2880, 400]
    },

    // 15. Poll Prediction (HTTP GET)
    {
      parameters: {
        method: "GET",
        url: "={{ $json.poll_url }}",
        sendHeaders: true,
        headerParameters: {
          parameters: [
            { name: "Authorization", value: "={{ 'Bearer ' + $vars.REPLICATE_API_TOKEN }}" },
            { name: "Accept", value: "application/json" }
          ]
        },
        options: { timeout: 15000 }
      },
      id: "poll-prediction",
      name: "Poll Prediction",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [3100, 600],
      onError: "continueRegularOutput"
    },

    // 16. Notify Step 4
    makeNotifyNode("step4", "Notify Step 4", 4, [3100, 400], "={{ $('Collect Predictions').first().json.project_id }}"),

    // 17. Wait 3 more minutes
    {
      parameters: { amount: 3, unit: "minutes" },
      id: "wait-3min",
      name: "Wait 3min",
      type: "n8n-nodes-base.wait",
      typeVersion: 1.1,
      position: [3320, 400]
    },

    // 18. Split for Poll 2
    {
      parameters: { jsCode: splitForPollCode },
      id: "split-poll-2",
      name: "Split for Poll 2",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [3540, 400]
    },

    // 19. Batch Poll 2 (SplitInBatches)
    {
      parameters: {
        batchSize: 1,
        options: {}
      },
      id: "batch-poll-2",
      name: "Batch Poll 2",
      type: "n8n-nodes-base.splitInBatches",
      typeVersion: 3,
      position: [3760, 400]
    },

    // 20. Poll Final (HTTP GET)
    {
      parameters: {
        method: "GET",
        url: "={{ $json.poll_url }}",
        sendHeaders: true,
        headerParameters: {
          parameters: [
            { name: "Authorization", value: "={{ 'Bearer ' + $vars.REPLICATE_API_TOKEN }}" },
            { name: "Accept", value: "application/json" }
          ]
        },
        options: { timeout: 15000 }
      },
      id: "poll-final",
      name: "Poll Final",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [3980, 600],
      onError: "continueRegularOutput"
    },

    // 21. Prepare Callback
    {
      parameters: { jsCode: prepareCallbackCode },
      id: "prepare-callback",
      name: "Prepare Callback",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [3980, 400]
    },

    // 22. Notify Step 5
    makeNotifyNode("step5", "Notify Step 5", 5, [4200, 400], "={{ $json.project_id }}"),

    // 23. Send Callback
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
        body: "={{ $('Prepare Callback').first().json.callbackBody }}",
        options: { timeout: 15000 }
      },
      id: "send-callback",
      name: "Send Callback",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [4420, 400]
    }
  ],
  connections: {
    // Linear flow
    "Webhook Trigger":     { main: [[{ node: "Notify Step 1",       type: "main", index: 0 }]] },
    "Notify Step 1":       { main: [[{ node: "Build Groq Request",  type: "main", index: 0 }]] },
    "Build Groq Request":  { main: [[{ node: "Call Groq API",       type: "main", index: 0 }]] },
    "Call Groq API":       { main: [[{ node: "Parse Story",         type: "main", index: 0 }]] },
    "Parse Story":         { main: [[{ node: "Notify Step 2",       type: "main", index: 0 }]] },
    "Notify Step 2":       { main: [[{ node: "Split Scenes",        type: "main", index: 0 }]] },
    "Split Scenes":        { main: [[{ node: "Batch Create",        type: "main", index: 0 }]] },

    // Loop 1: Create predictions one by one
    // SplitInBatches output 0 = loop (current batch), output 1 = done
    "Batch Create": { main: [
      [{ node: "Create Prediction",   type: "main", index: 0 }],  // output 0: loop
      [{ node: "Collect Predictions", type: "main", index: 0 }]   // output 1: done
    ]},
    "Create Prediction": { main: [[{ node: "Batch Create", type: "main", index: 0 }]] }, // loop back

    // Continue after all predictions created
    "Collect Predictions": { main: [[{ node: "Notify Step 3",  type: "main", index: 0 }]] },
    "Notify Step 3":       { main: [[{ node: "Wait 5min",      type: "main", index: 0 }]] },
    "Wait 5min":           { main: [[{ node: "Split for Poll", type: "main", index: 0 }]] },
    "Split for Poll":      { main: [[{ node: "Batch Poll",     type: "main", index: 0 }]] },

    // Loop 2: Poll predictions one by one
    "Batch Poll": { main: [
      [{ node: "Poll Prediction", type: "main", index: 0 }],  // output 0: loop
      [{ node: "Notify Step 4",   type: "main", index: 0 }]   // output 1: done
    ]},
    "Poll Prediction": { main: [[{ node: "Batch Poll", type: "main", index: 0 }]] }, // loop back

    // Continue after first poll
    "Notify Step 4":       { main: [[{ node: "Wait 3min",          type: "main", index: 0 }]] },
    "Wait 3min":           { main: [[{ node: "Split for Poll 2",   type: "main", index: 0 }]] },
    "Split for Poll 2":   { main: [[{ node: "Batch Poll 2",       type: "main", index: 0 }]] },

    // Loop 3: Final poll one by one
    "Batch Poll 2": { main: [
      [{ node: "Poll Final",        type: "main", index: 0 }],  // output 0: loop
      [{ node: "Prepare Callback",  type: "main", index: 0 }]   // output 1: done
    ]},
    "Poll Final": { main: [[{ node: "Batch Poll 2", type: "main", index: 0 }]] }, // loop back

    // Final steps
    "Prepare Callback":    { main: [[{ node: "Notify Step 5", type: "main", index: 0 }]] },
    "Notify Step 5":       { main: [[{ node: "Send Callback",  type: "main", index: 0 }]] }
  },
  active: false,
  settings: { executionOrder: "v1" }
};

require('fs').writeFileSync(
  'n8n_workflow.json',
  JSON.stringify(workflow, null, 2),
  'utf-8'
);

console.log('Workflow v9 generated successfully!');
console.log('Nodes:', workflow.nodes.length);
console.log('Loops: 3 SplitInBatches (Create, Poll, Final Poll)');
console.log('Flow:');
Object.entries(workflow.connections).forEach(([k, v]) => {
  const targets = v.main.map((arr, idx) =>
    arr.map(c => c.node).join(', ') + (v.main.length > 1 ? ` [out${idx}]` : '')
  );
  console.log('  ' + k + ' -> ' + targets.join(' | '));
});
