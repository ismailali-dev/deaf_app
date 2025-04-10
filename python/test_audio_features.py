import os
os.environ['OPENBLAS_NUM_THREADS'] = '1'

import json
import sys
import librosa
import numpy as np
import noisereduce as nr
import soundfile as sf

def remove_noise(audio_file):
    try:
        # Load audio file
        y, sr = librosa.load(audio_file, sr=None)

        # Apply noise reduction
        reduced_noise = nr.reduce_noise(y=y, sr=sr)

        # Save noise-reduced file
        clean_audio_path = audio_file.replace(".wav", "_clean.wav")
        sf.write(clean_audio_path, reduced_noise, sr)

        return clean_audio_path, reduced_noise, sr
    except Exception as e:
        return None, None, None

def extract_features(audio_file):
    try:
        # Remove noise from the audio
        clean_audio_path, y, sr = remove_noise(audio_file)

        if y is None:
            return {
                "status": "error",
                "message": "Noise reduction failed."
            }

        # Extract features from noise-reduced audio
        mfcc = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=13)
        chroma = librosa.feature.chroma_stft(y=y, sr=sr)
        spectral_contrast = librosa.feature.spectral_contrast(y=y, sr=sr)

        features = {
            "mfcc": mfcc.mean(axis=1).tolist(),
            "chroma": chroma.mean(axis=1).tolist(),
            "spectral_contrast": spectral_contrast.mean(axis=1).tolist(),
        }

        response = {
            "status": "success",
            "clean_audio": clean_audio_path,
            "features": features
        }
        return response

    except Exception as e:
        return {
            "status": "error",
            "message": str(e)
        }

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({
            "status": "error",
            "message": "Invalid arguments. Usage: python test_audio_features.py <audio_file_path>"
        }))
        sys.exit(1)

    audio_file = sys.argv[1]
    result = extract_features(audio_file)
    print(json.dumps(result))
