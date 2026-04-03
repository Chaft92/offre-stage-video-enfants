// Script to generate the n8n workflow JSON with proper escaping
// Run: node _generate_workflow.js
// v7 — Full pipeline: Groq story + 3x Replicate video (one per scene)

// ─── Code: Build Groq Request ───
const buildGroqCode = `
const webhookData = $('Webhook Trigger').first().json.body;
const theme = webhookData.theme;
const project_id = webhookData.project_id;

if (!project_id || !theme) {
  throw new Error('Missing project_id or theme from webhook');
}

const groqBody = {
  model: 'llama-3.3-70b-versatile',
  max_tokens: 4096,
  temperature: 0.7,
  response_format: { type: 'json_object' },
  messages: [
    {
      role: 'system',
      content: 'Tu es un auteur de livres pour enfants de 8 ans. Tu reponds UNIQUEMENT en JSON valide. Pas de texte avant ou apres le JSON. Pas de markdown.'
    },
    {
      role: 'user',
      content: 'Genere une courte histoire pour enfants sur le theme : \"' + theme + '\".\\nDecoupe-la en exactement 3 scenes.\\nReponds avec ce JSON exact :\\n{\\n  \"story\": \"texte complet\",\\n  \"moral\": \"la morale\",\\n  \"scenes\": [\\n    { \"scene_number\": 1, \"visual_description\": \"A colorful cartoon scene showing...\", \"narration\": \"texte narration francais\", \"duration_seconds\": 10 },\\n    { \"scene_number\": 2, \"visual_description\": \"A bright animated scene where...\", \"narration\": \"texte narration francais\", \"duration_seconds\": 10 },\\n    { \"scene_number\": 3, \"visual_description\": \"A cheerful cartoon finale with...\", \"narration\": \"texte narration francais\", \"duration_seconds\": 10 }\\n  ]\\n}'
    }
  ]
};

return [{ json: { project_id, theme, groqBody } }];
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
if (clean.indexOf('\`\`\`') >= 0) {
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
    duration_seconds: sc.duration_seconds || 10
  });
}

return [{ json: { project_id: project_id, theme: theme, story: parsed.story || '', moral: parsed.moral || '', scenes: scenes } }];
`.trim();

// ─── Code: Prepare 3 Scenes for Replicate ───
const prepareScenesCode = `
var data = $('Parse Story').first().json;
var scenes = data.scenes || [];
var result = { project_id: data.project_id };

for (var i = 0; i < 3; i++) {
  var sc = scenes[i];
  if (sc) {
    var prompt = sc.visual_description + '. Cartoon style animation for children, bright colors, child-friendly, smooth movement, 5 seconds.';
    result['body_' + (i + 1)] = JSON.stringify({ input: { prompt: prompt, prompt_optimizer: true } });
  } else {
    result['body_' + (i + 1)] = '';
  }
}

return [{ json: result }];
`.trim();

// ─── Code: Extract 3 Prediction URLs ───
const extractPredUrlsCode = `
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

function getUrl(nodeName) {
  try {
    var raw = $(nodeName).first().json;
    var p = parseResp(raw);
    if (p && p.id) {
      return (p.urls && p.urls.get) ? p.urls.get : 'https://api.replicate.com/v1/predictions/' + p.id;
    }
  } catch(e) {}
  return '';
}

return [{ json: {
  project_id: $('Parse Story').first().json.project_id,
  url_1: getUrl('Create Pred 1'),
  url_2: getUrl('Create Pred 2'),
  url_3: getUrl('Create Pred 3')
} }];
`.trim();

// ─── Code: Prepare Callback (extract video URLs from 6 poll nodes) ───
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

function extractVideoUrl(nodeName) {
  try {
    var raw = $(nodeName).first().json;
    var p = parseResp(raw);
    if (p && p.status === 'succeeded' && p.output) {
      var out = p.output;
      if (typeof out === 'string' && out.startsWith('http')) return out;
      if (Array.isArray(out) && out.length > 0 && typeof out[0] === 'string') return out[0];
    }
  } catch(e) {}
  return '';
}

var videoUrls = [];
for (var i = 1; i <= 3; i++) {
  var url = extractVideoUrl('Poll Final ' + i) || extractVideoUrl('Poll ' + i);
  videoUrls.push(url);
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

var mainVideoUrl = videoUrls.find(function(u) { return u && u.startsWith('http'); }) || 'https://demo-video-placeholder.example.com/video.mp4';

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

// ─── Helper: Replicate Create Prediction node ───
function makeCreatePred(id, name, bodyExpr, position) {
  return {
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
      body: bodyExpr,
      options: { timeout: 30000 }
    },
    id: id,
    name: name,
    type: "n8n-nodes-base.httpRequest",
    typeVersion: 4.2,
    position: position,
    onError: "continueRegularOutput"
  };
}

// ─── Helper: Poll Prediction node ───
function makePollNode(id, name, urlExpr, position) {
  return {
    parameters: {
      method: "GET",
      url: urlExpr,
      sendHeaders: true,
      headerParameters: {
        parameters: [
          { name: "Authorization", value: "={{ 'Bearer ' + $vars.REPLICATE_API_TOKEN }}" },
          { name: "Accept", value: "application/json" }
        ]
      },
      options: { timeout: 15000 }
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
  name: "Video Pipeline v7 — 3 Scenes",
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
    makeNotifyNode("step1", "Notify Step 1", 1, [480, 400], "={{ $json.body.project_id }}"),

    // 3. Build Groq Request
    {
      parameters: { jsCode: buildGroqCode },
      id: "build-groq",
      name: "Build Groq Request",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [720, 400]
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
      position: [960, 400]
    },

    // 5. Parse Story
    {
      parameters: { jsCode: parseStoryCode },
      id: "parse-story",
      name: "Parse Story",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1200, 400]
    },

    // 6. Notify Step 2 — story generated
    makeNotifyNode("step2", "Notify Step 2", 2, [1440, 400], "={{ $json.project_id }}"),

    // 7. Prepare 3 Scenes
    {
      parameters: { jsCode: prepareScenesCode },
      id: "prepare-scenes",
      name: "Prepare Scenes",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1680, 400]
    },

    // 8-10. Create 3 Replicate predictions
    makeCreatePred("create-pred-1", "Create Pred 1", "={{ $('Prepare Scenes').first().json.body_1 }}", [1920, 400]),
    makeCreatePred("create-pred-2", "Create Pred 2", "={{ $('Prepare Scenes').first().json.body_2 }}", [2160, 400]),
    makeCreatePred("create-pred-3", "Create Pred 3", "={{ $('Prepare Scenes').first().json.body_3 }}", [2400, 400]),

    // 11. Extract 3 Prediction URLs
    {
      parameters: { jsCode: extractPredUrlsCode },
      id: "extract-pred-urls",
      name: "Extract Pred URLs",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [2640, 400]
    },

    // 12. Notify Step 3 — video generation started
    makeNotifyNode("step3", "Notify Step 3", 3, [2880, 400], "={{ $json.project_id }}"),

    // 13. Wait 4 minutes (all 3 predictions process in parallel on Replicate)
    {
      parameters: { amount: 4, unit: "minutes" },
      id: "wait-4min",
      name: "Wait 4min",
      type: "n8n-nodes-base.wait",
      typeVersion: 1.1,
      position: [3120, 400]
    },

    // 14-16. Poll all 3 predictions (first check)
    makePollNode("poll-1", "Poll 1", "={{ $('Extract Pred URLs').first().json.url_1 }}", [3360, 400]),
    makePollNode("poll-2", "Poll 2", "={{ $('Extract Pred URLs').first().json.url_2 }}", [3600, 400]),
    makePollNode("poll-3", "Poll 3", "={{ $('Extract Pred URLs').first().json.url_3 }}", [3840, 400]),

    // 17. Notify Step 4 — checking videos
    makeNotifyNode("step4", "Notify Step 4", 4, [4080, 400], "={{ $('Extract Pred URLs').first().json.project_id }}"),

    // 18. Wait 2 more minutes
    {
      parameters: { amount: 2, unit: "minutes" },
      id: "wait-2min",
      name: "Wait 2min",
      type: "n8n-nodes-base.wait",
      typeVersion: 1.1,
      position: [4320, 400]
    },

    // 19-21. Final poll for all 3 predictions
    makePollNode("poll-final-1", "Poll Final 1", "={{ $('Extract Pred URLs').first().json.url_1 }}", [4560, 400]),
    makePollNode("poll-final-2", "Poll Final 2", "={{ $('Extract Pred URLs').first().json.url_2 }}", [4800, 400]),
    makePollNode("poll-final-3", "Poll Final 3", "={{ $('Extract Pred URLs').first().json.url_3 }}", [5040, 400]),

    // 22. Prepare Callback (extracts video URLs from poll results)
    {
      parameters: { jsCode: prepareCallbackCode },
      id: "prepare-callback",
      name: "Prepare Callback",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [5280, 400]
    },

    // 23. Notify Step 5 — finalizing
    makeNotifyNode("step5", "Notify Step 5", 5, [5520, 400], "={{ $json.project_id }}"),

    // 24. Send Callback
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
      position: [5760, 400]
    }
  ],
  connections: {
    "Webhook Trigger":     { main: [[{ node: "Notify Step 1",      type: "main", index: 0 }]] },
    "Notify Step 1":       { main: [[{ node: "Build Groq Request",  type: "main", index: 0 }]] },
    "Build Groq Request":  { main: [[{ node: "Call Groq API",       type: "main", index: 0 }]] },
    "Call Groq API":       { main: [[{ node: "Parse Story",         type: "main", index: 0 }]] },
    "Parse Story":         { main: [[{ node: "Notify Step 2",       type: "main", index: 0 }]] },
    "Notify Step 2":       { main: [[{ node: "Prepare Scenes",      type: "main", index: 0 }]] },
    "Prepare Scenes":      { main: [[{ node: "Create Pred 1",       type: "main", index: 0 }]] },
    "Create Pred 1":       { main: [[{ node: "Create Pred 2",       type: "main", index: 0 }]] },
    "Create Pred 2":       { main: [[{ node: "Create Pred 3",       type: "main", index: 0 }]] },
    "Create Pred 3":       { main: [[{ node: "Extract Pred URLs",   type: "main", index: 0 }]] },
    "Extract Pred URLs":   { main: [[{ node: "Notify Step 3",       type: "main", index: 0 }]] },
    "Notify Step 3":       { main: [[{ node: "Wait 4min",           type: "main", index: 0 }]] },
    "Wait 4min":           { main: [[{ node: "Poll 1",              type: "main", index: 0 }]] },
    "Poll 1":              { main: [[{ node: "Poll 2",              type: "main", index: 0 }]] },
    "Poll 2":              { main: [[{ node: "Poll 3",              type: "main", index: 0 }]] },
    "Poll 3":              { main: [[{ node: "Notify Step 4",       type: "main", index: 0 }]] },
    "Notify Step 4":       { main: [[{ node: "Wait 2min",           type: "main", index: 0 }]] },
    "Wait 2min":           { main: [[{ node: "Poll Final 1",        type: "main", index: 0 }]] },
    "Poll Final 1":        { main: [[{ node: "Poll Final 2",        type: "main", index: 0 }]] },
    "Poll Final 2":        { main: [[{ node: "Poll Final 3",        type: "main", index: 0 }]] },
    "Poll Final 3":        { main: [[{ node: "Prepare Callback",    type: "main", index: 0 }]] },
    "Prepare Callback":    { main: [[{ node: "Notify Step 5",       type: "main", index: 0 }]] },
    "Notify Step 5":       { main: [[{ node: "Send Callback",       type: "main", index: 0 }]] }
  },
  active: false,
  settings: { executionOrder: "v1" }
};

require('fs').writeFileSync(
  'n8n_workflow.json',
  JSON.stringify(workflow, null, 2),
  'utf-8'
);

console.log('Workflow v7 generated successfully!');
console.log('Nodes:', workflow.nodes.length);
console.log('Flow:');
Object.entries(workflow.connections).forEach(([k, v]) => {
  console.log('  ' + k + ' -> ' + v.main[0].map(c => c.node).join(', '));
});
