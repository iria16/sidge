import json

with open('/mnt/c/Users/iriamar/.gemini/antigravity/brain/a756236e-3695-47d6-af25-9e428f480552/.system_generated/logs/transcript.jsonl', 'r', encoding='utf-8') as f:
    for i, line in enumerate(f):
        if 2485 <= i <= 2615:
            data = json.loads(line)
            if data.get('type') == 'USER_INPUT':
                print(f"USER {i}: {data.get('content')[:120]}")
            for tc in data.get('tool_calls', []):
                cmd = tc.get('args', {}).get('CommandLine', '')
                path = tc.get('args', {}).get('Path', '')
                print(f"STEP {i}: {tc['name']} {cmd[:60]} {path}")
