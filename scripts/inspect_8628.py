import json

with open('/mnt/c/Users/iriamar/.gemini/antigravity/brain/1ca46780-5dd4-4130-8568-8a1202451bc9/.system_generated/logs/transcript.jsonl') as f:
    for line in f:
        d = json.loads(line)
        if d.get('step_index') == 8628:
            print("=== STEP 8628 ===")
            content = d.get('content', '')
            for l in content.split('\n'):
                if any(x in l for x in ['cuerpoNomina', 'datosNomina', 'filtrosNomina', 'agrupar', 'vistaAgrupada']):
                    print(l)
