---
description: Produce a polished product video/commercial in Apple keynote style
argument-hint: <brief description of what the video should showcase>
model: opus
---

# Video Production Skill

Produce a polished 1920×1080 H.264 video with original MIDI music, entirely from the terminal. The visual style is **Apple keynote**: clean white backgrounds, dark text, screenshots presented as floating cards with rounded corners and drop shadows.

## Assets

Reusable assets live in `.claude/video-assets/`:
- **Lexend.ttf** — Brand font (converted from woff2)
- **soundfont.sf2** — GeneralUser GS SoundFont for FluidSynth rendering
- **compose.py** — Reference MIDI composition script (100 BPM, C major, piano/pad/bass/drums)
- **generate_frames_v2.py** — Reference frame generator with all the style patterns

## Working Directory

Always use `/tmp/oc-video/` as the working directory. Create subdirectories:
```
/tmp/oc-video/
├── screenshots/    # Playwright captures
├── frames/         # Final sequenced frames for ffmpeg
├── scenes/s1..sN/  # Per-scene frame directories
└── music/          # MIDI + WAV
```

## Visual Style Rules (MANDATORY)

### Text Scenes (white background)
- Background: `(255, 255, 255)` pure white
- Primary text: `(28, 28, 30)` Apple system black
- Accent text: `(59, 130, 246)` brand blue
- Secondary text: `(142, 142, 147)` Apple system gray
- Muted text: `(174, 174, 178)`
- Code/terminal text: `(52, 199, 89)` Apple green
- Font: Lexend (`.claude/video-assets/Lexend.ttf`)
- All text fade-ins use **ease-out cubic** easing: `1 - (1 - t)³`
- Text slides up 8-15px as it fades in (parallax feel)
- Fade duration: 20-25 frames (~0.7-0.8s)
- Generous spacing between lines (80-100px)

### Screenshot Scenes (white background + floating card)
- Screenshot is placed on a white canvas at **72-75% scale**
- **Rounded corners** (radius 12-16px) via PIL mask
- **Drop shadow**: 20-24px blur, 50-80 opacity, offset 4px down
- Label text appears ABOVE the screenshot, centered
- Subtle **Ken Burns** zoom (1.0× → 1.02×) for motion
- Cross-dissolve transitions between screenshots: 30 frames with ease-out

### Scene Transitions
- 15-frame cross-dissolve between scenes (0.5s)
- Blend factor ramps to 0.6 (not full 1.0) for subtlety

### Animation Scenes (hero, org chart)
- Capture 90 frames (3s loop) at 33ms intervals via Playwright
- For overlaid text on animation: white semi-transparent bar (`alpha=220`) behind text
- Loop frames for scene duration

## Screenshot Capture Protocol

1. **Resize browser** to 1920×1080 FIRST: `browser_resize(1920, 1080)`
2. Navigate to the target page
3. Wait for data to load (2-3s minimum)
4. Take screenshot — this gives native-resolution captures with no scaling
5. For animated pages (canvas/WebGL): capture 90 frames in a loop with 33ms intervals

### App pages (http://opencompany.test)
- Dashboard, Chat, Approvals, Org Chart, Calendar, etc.
- Wait for async data to load before capturing

### Website pages (http://localhost:4322)
- Start preview server: `cd opencompany-website && npm run preview`
- For hero: hide header/footer via DOM manipulation, set hero `minHeight: 100vh`

## Music Composition

Use `midiutil` (Python) to compose a MIDI file, then render with FluidSynth:

```bash
# Compose
python3 music_script.py  # outputs soundtrack.mid

# Render to WAV
fluidsynth -ni -F /tmp/oc-video/music/soundtrack.wav -r 44100 \
  .claude/video-assets/soundfont.sf2 /tmp/oc-video/music/soundtrack.mid
```

### Music style
- Tempo: ~100 BPM, Key: C major
- Channels: Piano (ch0, program 0), Pad (ch1, program 89), Bass (ch2, program 33), Drums (ch9)
- Progression: Cmaj7 → Am7 → Fmaj7 → G
- Structure: Sparse intro → build → groove → peak → wind down → outro
- Match music sections to video scene timing
- Reference: `.claude/video-assets/compose.py`

## Frame Generation

Write a Python script using **Pillow (PIL)** for all frame generation. Key functions from the reference:

```python
from PIL import Image, ImageDraw, ImageFont, ImageFilter

def add_rounded_shadow(screenshot, corner_radius=16, shadow_size=24, shadow_opacity=60):
    """Rounded corners + drop shadow for screenshot cards."""

def place_screenshot_on_white(screenshot, scale=0.75, y_offset=110):
    """Center screenshot as floating card on white canvas."""

def ken_burns(img, frame, total, start_s=1.0, end_s=1.02):
    """Subtle zoom for motion on static screenshots."""

def ease_out_cubic(t):
    return 1 - (1 - t) ** 3

def cross_dissolve(a, b, t):
    return Image.blend(a.convert('RGB'), b.convert('RGB'), t)
```

See `.claude/video-assets/generate_frames_v2.py` for the full reference implementation.

## Final Assembly

```bash
ffmpeg -framerate 30 \
  -i /tmp/oc-video/frames/frame_%05d.png \
  -i /tmp/oc-video/music/soundtrack.wav \
  -c:v libx264 -preset slow -crf 18 \
  -c:a aac -b:a 192k \
  -pix_fmt yuv420p \
  -shortest \
  -movflags +faststart \
  /tmp/oc-video/output.mp4 -y
```

Then copy to project: `cp /tmp/oc-video/output.mp4 tmp/<name>.mp4`

## Workflow Summary

1. Plan scenes based on the user's description (storyboard with timing)
2. Create working directories
3. Capture screenshots at native 1920×1080 via Playwright
4. Compose MIDI music matched to scene timing
5. Render MIDI → WAV via FluidSynth
6. Generate all scene frames with Pillow (white bg, floating cards, eased animations)
7. Add cross-dissolve transitions between scenes
8. Sequence all frames and assemble with ffmpeg
9. Copy final MP4 to `tmp/` and open for preview

Always spot-check frames before final assembly by reading PNG files visually.
