# Design Conversations

## Development Setup

### Using VS Code Devcontainer (Recommended)

Open the project in VS Code with the Dev Containers extension. The container
includes all dependencies pre-configured: Ruby, PHP 8.4, Bundler, Composer,
lame, eyeD3, ImageMagick, and Internet Archive CLI.

### Using Docker Directly

```bash
make docker-build    # Build the development image
make docker-shell    # Start interactive shell in container
make install         # Install Ruby and PHP dependencies
```

### Prerequisites (Manual Setup)

If not using the container, install:

* Ruby + Bundler
* PHP 8.4 + Composer
* `lame` (MP3 encoding)
* `eyeD3` (ID3 tagging)
* `imagemagick` (episode artwork)
* `ia` / internetarchive (uploads)

### Local Preview

```bash
make serve         # Serve site at http://localhost:4000
make serve-drafts  # Include draft posts
```

## Configuration

### Environment Variables

Create `src/.env` with:

```bash
AIRTABLE_KEY=<your-api-key>
AIRTABLE_BASE=<your-base-id>
AIRTABLE_TABLE=<your-table-name>
```

### Airtable Fields

| Field | Description |
| ----- | ----------- |
| episodeId | Episode number |
| seasonNum | Season number |
| state | "Draft" or "Published" |
| date | Publication date |
| guestId | Guest identifier (used in filenames) |
| title | Episode title |
| tags | Episode tags |
| showNotes1/2/3 | Show notes in Markdown |
| mp3EmbedUrl | Embedded player URL |
| photoCredit | Guest photo attribution |
| includeInPodcastFeed | Boolean; skips reprocessing if true |

## Conventions

* Episode post file name format: `YYYY-MM-DD-episode-<zero-padded episode number>-<interviewee name>.md`
* Guest photo spec: 150px x 150px, desaturated 60%
* Media file name format: `YYYY-MM-DD-episode-<zero-padded episode number>-<interviewee name>.mp3`
* ID3 tags (v2.4 via eyeD3):

| Tag | Source |
| --- | ------ |
| title | Formatted episode title |
| artist | "Geoff Fitzpatrick" |
| album | "Design Conversations Podcast" |
| album-artist | "Geoff Fitzpatrick" |
| track | Episode ID |
| disc-num | Season number |
| genre | "Podcast" |
| recording-date | Episode date |
| release-year | Year from episode date |
| publisher | "DesignConversations.net" |
| image | artwork.jpg (BAND type) |
| comment | Formatted episode notes |

## Tools

The following tools follow the general pattern of reading episode data
from Airtable, and processing each episode record in some way.

`01-build-episode-posts.php` generates a Jekyll post page for each episode,
including front-matter and page content.

`02-encode-mp3s.php` loads a matching MP3 file for each episode record,
encoding it to CBR 128kbps via lame.

`03-tag-mp3s.php` loads an encoded MP3 file for each episode record, adding
episode data as ID3 tags.

`04-upload-mp3s.php` uploads encoded and tagged MP3 files to Internet Archive
for each episode record, if they haven't been uploaded already.

Run these tools via Make targets in the root `Makefile`. Use `make help`
to see available targets, including `-force` variants that reprocess all
episodes rather than skipping published ones.

### Directory Structure

MP3 files progress through these directories:

```text
src/mp3s/orig/    # Original recordings
src/mp3s/enc/     # Encoded (CBR 128kbps)
src/mp3s/tag/     # Tagged with ID3 metadata
```

### Episode States

Episodes are processed based on their `state` field:

* **Draft** - Processed by tools
* **Published** - Processed by tools; skipped if `includeInPodcastFeed` is true (use `--force` to override)

## To Do (Deprecated)

This section tracked initial development and is no longer maintained.
