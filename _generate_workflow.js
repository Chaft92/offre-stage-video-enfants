const buildGroqCode = `
var webhookData = $('Webhook Trigger').first().json.body;
var theme = webhookData.theme;
var project_id = webhookData.project_id;
var style = webhookData.style || 'cartoon';

if (!project_id || !theme) {
  throw new Error('Missing project_id or theme from webhook');
}

var styleMap = {
  cartoon: 'A colorful cartoon',
  watercolor: 'A beautiful watercolor painting',
  pixel: 'A detailed pixel art scene',
  anime: 'A vibrant anime style illustration'
};
var stylePrefix = styleMap[style] || styleMap.cartoon;

var groqBody = {
  model: 'llama-3.3-70b-versatile',
  max_tokens: 8192,
  temperature: 0.65,
  response_format: { type: 'json_object' },
  messages: [
    {
      role: 'system',
      content: 'Tu es un directeur d ecriture et storyboarder senior specialise en videos educatives premium pour enfants (6-10 ans). Tu dois produire un resultat cinematographique, pedagogique et coherent. Tu reponds UNIQUEMENT en JSON valide, sans markdown.'
    },
    {
      role: 'user',
      content: 'Cree un FILM EDUCATIF PREMIUM sur : "' + theme + '".\\n\\nOBJECTIF : video finale de niveau recruteur, riche, detaillee, emotionnelle et pedagogique.\\n\\nCONTRAINTES OBLIGATOIRES :\\n- EXACTEMENT 8 scenes, numerotees 1 a 8, sans trou.\\n- Structure fixe : scenes 1-2 = introduction, 3-6 = development, 7-8 = conclusion.\\n- Personnages et decor coherents d une scene a l autre (continuite visuelle).\\n- Chaque scene doit apporter une progression narrative concrete.\\n- Ton bienveillant, non choquant, adapte enfants 6-10 ans.\\n\\nQUALITE NARRATIVE :\\n- story : 180 a 260 mots en francais.\\n- moral : 1 phrase claire et memorisable.\\n- narration par scene : 55 a 95 mots en francais naturel, varie, vivant.\\n- duration_seconds par scene : entier entre 20 et 35.\\n\\nQUALITE VISUELLE :\\n- visual_description : en ANGLAIS, 90 a 150 mots, tres detaillee, cinematographique, avec composition, camera, lumiere, ambiance, emotion, actions precises. Commencer par "' + stylePrefix + '".\\n- image_prompt : en ANGLAIS, 30 a 60 mots, concis, optimise generation image (resume visuel de la scene).\\n\\nVOIX :\\n- voice doit etre une valeur parmi : narratrice, narrateur, enfant_fille, enfant_garcon.\\n- Varier les voix selon le contexte; ne pas mettre la meme voix partout.\\n\\nFORMAT JSON STRICT :\\n{\\n  "story": "...",\\n  "moral": "...",\\n  "scenes": [\\n    {\\n      "scene_number": 1,\\n      "part": "introduction",\\n      "visual_description": "...",\\n      "image_prompt": "...",\\n      "narration": "...",\\n      "duration_seconds": 24,\\n      "voice": "narrateur"\\n    }\\n  ]\\n}\\n\\nAucun texte hors JSON.'
    }
  ]
};

return [{ json: { project_id: project_id, theme: theme, groqBody: groqBody } }];
`.trim();

const parseStoryCode = `
var project_id = $('Build Groq Request').first().json.project_id;
var theme = $('Build Groq Request').first().json.theme;
var input = $input.first().json;
var TARGET_SCENES = 8;

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

function choosePart(index) {
  if (index < 2) return 'introduction';
  if (index >= TARGET_SCENES - 2) return 'conclusion';
  return 'development';
}

function chooseVoice(index, part) {
  var pattern = ['narrateur', 'narratrice', 'enfant_fille', 'narrateur', 'enfant_garcon', 'narratrice', 'enfant_fille', 'narrateur'];
  if (part === 'conclusion' && index >= TARGET_SCENES - 2) {
    return index === TARGET_SCENES - 2 ? 'narratrice' : 'narrateur';
  }
  return pattern[index % pattern.length];
}

function sanitizeText(v) {
  return String(v || '').replace(/\\s+/g, ' ').trim();
}

function enrichNarration(narration) {
  var txt = sanitizeText(narration);
  if (txt.length < 150) {
    txt += ' Le narrateur prend le temps d expliquer les emotions, les choix des personnages et ce que les enfants peuvent retenir dans la vie quotidienne.';
  }
  return txt.slice(0, 950);
}

function enrichVisual(visual, stylePrefix) {
  var txt = sanitizeText(visual);
  if (txt.length < 120) {
    txt = stylePrefix + ' cinematic scene with expressive characters, layered environment, controlled depth of field, detailed lighting, emotional clarity, and clear storytelling action.';
  }
  return txt.slice(0, 1400);
}

function buildImagePrompt(scene, stylePrefix) {
  var prompt = sanitizeText(scene.image_prompt || '');
  if (!prompt) {
    prompt = sanitizeText(scene.visual_description || '');
  }
  if (!prompt) {
    prompt = stylePrefix + ' cinematic animated still, coherent character design, detailed environment, emotional action';
  }
  return prompt.slice(0, 420);
}

var scenes = [];
for (var s = 0; s < Math.min(parsed.scenes.length, TARGET_SCENES); s++) {
  var sc = parsed.scenes[s];
  var part = ['introduction', 'development', 'conclusion'].includes(sc.part) ? sc.part : choosePart(s);
  var voice = ['narratrice', 'narrateur', 'enfant_fille', 'enfant_garcon'].includes(sc.voice) ? sc.voice : chooseVoice(s, part);

  scenes.push({
    scene_number: s + 1,
    part: part,
    visual_description: enrichVisual(sc.visual_description, 'A colorful cinematic animated frame'),
    image_prompt: buildImagePrompt(sc, 'A colorful cinematic animated frame'),
    narration: enrichNarration(sc.narration),
    duration_seconds: Math.max(20, Math.min(35, Number(sc.duration_seconds || 24))),
    voice: voice
  });
}

while (scenes.length < TARGET_SCENES) {
  var i = scenes.length;
  var p = choosePart(i);
  scenes.push({
    scene_number: i + 1,
    part: p,
    visual_description: 'A colorful cinematic animated frame with consistent character design, emotional expressions, dynamic composition, rich environment details, dramatic yet soft lighting, and pedagogical storytelling focus.',
    image_prompt: 'cinematic animated still, expressive character close-up, coherent environment, emotional storytelling, high detail, volumetric light',
    narration: 'La scene continue l histoire avec des actions claires, des emotions lisibles et une progression logique. Le ton reste pedagogique, rassurant et inspire les enfants a comprendre, communiquer et agir avec bienveillance.',
    duration_seconds: 24,
    voice: chooseVoice(i, p)
  });
}

var story = sanitizeText(parsed.story || '');
if (story.length < 280) {
  story += ' Cette histoire suit un parcours progressif: observation du probleme, comprehension des emotions, recherche d aide, experimentation, cooperation et resolution positive. Chaque scene illustre une etape concrete avec des exemples que les enfants peuvent reutiliser dans leur quotidien.';
}

var moral = sanitizeText(parsed.moral || 'Demander de l aide et parler de ses emotions permet de trouver des solutions et de grandir avec les autres.');

return [{ json: { project_id: project_id, theme: theme, story: story.slice(0, 2200), moral: moral.slice(0, 300), scenes: scenes } }];
`.trim();

const prepareCallbackCode = `
var storyData = $('Parse Story').first().json;

var scenes = [];
for (var s = 0; s < storyData.scenes.length; s++) {
  var sc = storyData.scenes[s];
  scenes.push({
    scene_number: sc.scene_number,
    part: sc.part,
    visual_description: sc.visual_description,
    image_prompt: sc.image_prompt,
    narration: sc.narration,
    duration_seconds: sc.duration_seconds,
    voice: sc.voice,
    video_url: ''
  });
}

var callbackBody = JSON.stringify({
  project_id: storyData.project_id,
  video_url: 'pending',
  story_text: storyData.story,
  moral: storyData.moral,
  scenes_json: scenes
});

return [{ json: { callbackBody: callbackBody, project_id: storyData.project_id } }];
`.trim();

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

const workflow = {
  name: "AI Kids Video Generator v14",
  nodes: [
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
      typeVersion: 2,
      position: [260, 400],
      webhookId: "video-pipeline"
    },
    makeNotifyNode("step1", "Notify Step 1", 1, [480, 400], "={{ $json.body.project_id }}"),
    {
      parameters: { jsCode: buildGroqCode },
      id: "build-groq",
      name: "Build Groq Request",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [700, 400]
    },
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
        options: { timeout: 120000 }
      },
      id: "call-groq",
      name: "Call Groq API",
      type: "n8n-nodes-base.httpRequest",
      typeVersion: 4.2,
      position: [920, 400],
      retryOnFail: true,
      maxTries: 3,
      waitBetweenTries: 30000
    },
    {
      parameters: { jsCode: parseStoryCode },
      id: "parse-story",
      name: "Parse Story",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1140, 400]
    },
    makeNotifyNode("step2", "Notify Step 2", 2, [1360, 400], "={{ $json.project_id }}"),
    {
      parameters: { jsCode: prepareCallbackCode },
      id: "prepare-callback",
      name: "Prepare Callback",
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1580, 400]
    },
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
    "Webhook Trigger":    { main: [[{ node: "Notify Step 1",      type: "main", index: 0 }]] },
    "Notify Step 1":      { main: [[{ node: "Build Groq Request",  type: "main", index: 0 }]] },
    "Build Groq Request": { main: [[{ node: "Call Groq API",       type: "main", index: 0 }]] },
    "Call Groq API":      { main: [[{ node: "Parse Story",         type: "main", index: 0 }]] },
    "Parse Story":        { main: [[{ node: "Notify Step 2",       type: "main", index: 0 }]] },
    "Notify Step 2":      { main: [[{ node: "Prepare Callback",    type: "main", index: 0 }]] },
    "Prepare Callback":   { main: [[{ node: "Send Callback",       type: "main", index: 0 }]] }
  },
  active: false,
  settings: { executionOrder: "v1" }
};

require('fs').writeFileSync('n8n_workflow.json', JSON.stringify(workflow, null, 2), 'utf-8');
console.log('Workflow v14 generated — ' + workflow.nodes.length + ' nodes, onReceived mode');