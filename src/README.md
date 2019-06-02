# Design Conversations

## Conventions

* Episode post file name format: `YYYY-MM-DD-episode-<zero-padded episode number>-<interviewee name>.md`
* Guest photo spec: 150px x 150px, desaturated 60%
* Media file name format: `YYYY-MM-DD-episode-<zero-padded episode number>-<interviewee name>.mp3`
* ID3 tags:

## Tools

The following tools follow the general pattern of reading episode data
from the `episode-data.ods` spreadsheet, and processing each episode
record in some way.

`build-episode-posts.php` generates a Jekyll post page for each episode,
including front-matter and page content.

`encode-mp3s.php` loads a matching MP3 file for each episode record,
encoding it to a standard encoding specification.

`tag-mp3s.php` loads an encoded MP3 file for each episode record, adding
episode data as ID3 tags.

`upload-mp3s.php` uploads encoded and tagged MP3 files for each episode
record, if they haven't been uploaded already.

## To Do

### Content 

- [x] Episode 1
- [x] Episode 2
- [x] About
- [x] Privacy

### Drafts

- [ ] Episode 3
- [ ] Episode 4
- [ ] Episode 5

### Misc

- [ ] Disqus
- [ ] Podcast feed
- [ ] Podcast registration
- [ ] Test Favicons
- [ ] Test Site
- [x] Analytics
- [x] Favicons
- [x] Share buttons

### Style

- [x] Page width
- [x] Fonts
- [x] Logo style

### Footer

- [x] Powered by...
- [x] Facebook link
