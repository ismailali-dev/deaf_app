import sys
from gtts import gTTS

text = sys.argv[1]
output_path = sys.argv[2]

try:
    tts = gTTS(text)
    tts.save(output_path)
    print('{"status": "success", "message": "TTS generated"}')
except Exception as e:
    print('{"status": "error", "message": "' + str(e).replace('"', "'") + '"}')
