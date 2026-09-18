# Transcriptions

We use `whisper.cpp` to transcribe audio files to text for display.

This process will work on most medium-sized web servers without any additional
configuration.

Turn the feature on or off with the **Transcription** toggle under
[System Features](system/index.md#system-features). Transcriptions appear on call records
and can be included in [Voicemail Digests](utilities/voicemail-digest.md).

## Requirements

`sudo apt install git make cmake ccache`

## Installation

```bash
git clone https://github.com/ggerganov/whisper.cpp.git /opt/whisper.cpp

# Environment specific - this will be the user that runs your web server
# sudo chown -R <your webserver user> /opt/whisper.cpp

cd /opt/whisper.cpp

# You can also choose any model of your choice here
# See supported models by running bash ./models/download-ggml-model.sh without any parameters
bash ./models/download-ggml-model.sh base.en

# Make everything so we can use it!
cmake -B build
cmake --build build --config Release 
```

## Customization

If you do customize which model you use, be sure to update your `.env` file with your
chosen `WHISPER_MODEL`.

You can also pass custom parameters using `WHISPER_COMMAND_PARAMS`, and install
whisper.cpp somewhere other than `/opt/whisper.cpp` by setting `WHISPER_PROJECT_ROOT`.

| Variable | Default |
|----------|---------|
| `WHISPER_PROJECT_ROOT` | `/opt/whisper.cpp` |
| `WHISPER_MODEL` | `ggml-base.en.bin` |
| `WHISPER_COMMAND_PARAMS` | *(empty)* |

### Example 

Here's an example using the quantized version.

1. First, we grab the new model we want:

```bash
bash ./models/download-ggml-model.sh base-q5_1
```

2: Next, in our `.env` file:

```
WHISPER_MODEL="ggml-base-q5_1.bin"
WHISPER_COMMAND_PARAMS="--threads 1 --processors 1 --language auto --no-gpu --translate"
```
