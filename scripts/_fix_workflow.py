import json

WORKFLOW = r'c:\Users\Julien\Projet_Test_Stage\n8n_workflow.json'

with open(WORKFLOW, 'r', encoding='utf-8') as f:
    data = json.load(f)


def strip_comment_lines(code: str) -> str:
    lines = code.split('\n')
    result, prev_blank = [], False
    for line in lines:
        if line.strip().startswith('//'):
            continue
        blank = line.strip() == ''
        if blank and prev_blank:
            continue
        result.append(line)
        prev_blank = blank
    return '\n'.join(result).strip()


EXTRACT_SCENE_RESULT = (
    "const runwayStatus = $input.first().json;\n"
    "const taskCtx      = $('Store Task Info').first().json;\n"
    "\n"
    "const videoUrl = runwayStatus.output?.[0] ?? runwayStatus.outputUrl ?? '';\n"
    "if (!videoUrl) {\n"
    "  throw new Error('Runway SUCCEEDED but no output URL found: ' + JSON.stringify(runwayStatus));\n"
    "}\n"
    "\n"
    "const staticData = $getWorkflowStaticData('global');\n"
    "if (!staticData[taskCtx.project_id]) {\n"
    "  staticData[taskCtx.project_id] = [];\n"
    "}\n"
    "staticData[taskCtx.project_id].push({\n"
    "  scene_number: taskCtx.scene_number,\n"
    "  video_url:    videoUrl,\n"
    "  audio_path:   taskCtx.audio_path,\n"
    "  duration:     taskCtx.duration,\n"
    "  narration:    taskCtx.narration,\n"
    "  visual:       taskCtx.visual,\n"
    "});\n"
    "\n"
    "return [{\n"
    "  json: {\n"
    "    project_id:   taskCtx.project_id,\n"
    "    story:        taskCtx.story,\n"
    "    all_scenes:   taskCtx.all_scenes,\n"
    "    scene_number: taskCtx.scene_number,\n"
    "    video_url:    videoUrl,\n"
    "    audio_path:   taskCtx.audio_path,\n"
    "    duration:     taskCtx.duration,\n"
    "    narration:    taskCtx.narration,\n"
    "    visual:       taskCtx.visual,\n"
    "  }\n"
    "}];"
)

PREPARE_FFMPEG_DATA = (
    "const allItems   = $input.all();\n"
    "const firstScene = allItems[0].json;\n"
    "\n"
    "const staticData      = $getWorkflowStaticData('global');\n"
    "const collectedScenes = (staticData[firstScene.project_id] || [])\n"
    "  .sort((a, b) => a.scene_number - b.scene_number);\n"
    "delete staticData[firstScene.project_id];\n"
    "\n"
    "if (collectedScenes.length === 0) {\n"
    "  throw new Error('No scene results in static data for project ' + firstScene.project_id);\n"
    "}\n"
    "\n"
    "const scenesForFfmpeg = collectedScenes.map(s => ({\n"
    "  scene_number:       s.scene_number,\n"
    "  video_url:          s.video_url,\n"
    "  audio_url:          s.audio_path,\n"
    "  narration:          s.narration,\n"
    "  visual_description: s.visual,\n"
    "  duration_seconds:   s.duration,\n"
    "}));\n"
    "\n"
    "const scenesB64  = Buffer.from(JSON.stringify(scenesForFfmpeg)).toString('base64');\n"
    "const story      = firstScene.story || '';\n"
    "const allScenes  = firstScene.all_scenes || scenesForFfmpeg;\n"
    "\n"
    "return [{\n"
    "  json: {\n"
    "    project_id:  firstScene.project_id,\n"
    "    story,\n"
    "    all_scenes:  allScenes,\n"
    "    scenes_b64:  scenesB64,\n"
    "    output_path: `/storage/videos/${firstScene.project_id}.mp4`,\n"
    "  }\n"
    "}];"
)

for node in data['nodes']:
    name = node.get('name', '')
    p    = node.get('parameters', {})

    if name in ('Build Claude Request', 'Prepare Scene Data'):
        p['jsCode'] = strip_comment_lines(p['jsCode'])

    elif name == 'Parse Claude Response':
        code = strip_comment_lines(p['jsCode'])
        code = code.replace(
            "Impossible de parser la r\u00e9ponse JSON de Claude: ",
            "Failed to parse Claude JSON response: "
        )
        code = code.replace(
            "Claude n\\'a pas retourn\u00e9 de sc\u00e8nes valides.",
            "Claude returned no valid scenes."
        )
        p['jsCode'] = code

    elif name == 'Store Task Info':
        code = strip_comment_lines(p['jsCode'])
        code = code.replace(
            "Runway n\\'a pas retourn\u00e9 de task ID. R\u00e9ponse: ",
            "Runway returned no task ID: "
        )
        p['jsCode'] = code

    elif name == 'Extract Scene Result':
        p['jsCode'] = EXTRACT_SCENE_RESULT

    elif name == 'Prepare FFmpeg Data':
        p['jsCode'] = PREPARE_FFMPEG_DATA

    elif name == 'Prepare Callback Payload':
        code = strip_comment_lines(p['jsCode'])
        code = code.replace(
            "assemble_video.py n\\'a pas retourn\u00e9 de chemin. stderr: ",
            "assemble_video.py returned no output path. stderr: "
        )
        p['jsCode'] = code

    elif name == 'Run FFmpeg Assembly':
        p['command'] = (
            '=python3 /scripts/assemble_video.py'
            ' --scenes-b64 "{{ $json.scenes_b64 }}"'
            ' --output {{ $json.output_path }}'
        )

    # Add X-N8N-Secret + Accept headers to every HTTP node that calls Laravel
    if node.get('type') == 'n8n-nodes-base.httpRequest':
        url = p.get('url', '')
        if '$vars.LARAVEL_URL' in url:
            hp = p.setdefault('headerParameters', {})
            params_list = hp.setdefault('parameters', [])
            if not any(h.get('name') == 'X-N8N-Secret' for h in params_list):
                params_list.append({"name": "X-N8N-Secret", "value": "={{ $vars.N8N_WEBHOOK_SECRET }}"})
            if not any(h.get('name') == 'Accept' for h in params_list):
                params_list.append({"name": "Accept", "value": "application/json"})

with open(WORKFLOW, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=2)

print("n8n_workflow.json updated OK")
