<?php

// Config for internal usage of whisper.cpp

return [
    'project_root' => env('WHISPER_PROJECT_ROOT', '/opt/whisper.cpp'),
    'model' => env('WHISPER_MODEL', 'ggml-base.en.bin'),
    'command_params' => env('WHISPER_COMMAND_PARAMS', ''),
];
