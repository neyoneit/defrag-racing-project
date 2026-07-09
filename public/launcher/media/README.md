# Launcher showcase media

Files served statically at `/launcher/media/...` and listed in
`resources/js/Pages/Launcher.vue` (`showcaseSlides`). Drop the files here with
these names and the showcase lights up automatically. To add/remove/reorder
slides, edit the `showcaseSlides` array.

Expected files (current slide list):

| file               | type  | notes                                                  |
|--------------------|-------|--------------------------------------------------------|
| `demo.mp4`         | video | short looping screen capture (H.264 mp4). Keep it small (a few MB, ~10-20s). |
| `demo-poster.webp` | image | poster frame shown before the video plays              |
| `dashboard.webp`   | image | demo auto-backup dashboard                             |
| `servers.webp`     | image | server browser                                         |
| `maps.webp`        | image | maps browser                                           |

Guidelines:
- **Aspect ratio 16:9** (the stage is `aspect-video`); other ratios get cropped (`object-cover`).
- Screenshots: `.webp`, ~1600px wide, aim for < 300 kB each.
- Video: `.mp4` (H.264) plays everywhere. Muted + autoplay; keep it short and light so the page loads fast.
