# Screen Captures

The screen capture feature allows you to convert Amtelco vlogStream video files into mp4 files, caches them, and allows playback through the Mission Control application.

You can turn this feature on or off with the **Screen Captures** toggle under
[System Features](system/index.md#system-features).

Screen captures appear on call records alongside recordings, and calls that have one can be
isolated with the *Has screen capture* filter in [CSV Export](utilities/csv-export.md).

> There is no other configuration for this feature.

## Requirements

Conversion runs on the Mission Control server using `ffmpeg`, which is installed during
provisioning. Jobs run on the `ffmpeg` queue, so the first playback of a given call waits
for the conversion; afterwards the mp4 is served from cache.

> There is no additional setup required.
