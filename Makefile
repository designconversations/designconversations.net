.DEFAULT_GOAL := help

.PHONY: help install build build-drafts serve serve-drafts \
        01-build-episodes 01-build-episodes-force \
        02-encode-mp3s 02-encode-mp3s-force \
        03-tag-mp3s 03-tag-mp3s-force \
        04-upload-mp3s 04-upload-mp3s-force \
        docker-build docker-shell

help: ## Show this help (default).
	@# https://marmelab.com/blog/2016/02/29/auto-documented-makefile.html
	@# Add target help description following the target name, prefixed by a double-hash (##)
	@echo ""
	@echo "#######################################"
	@echo "## Design Conversations make targets ##"
	@echo "#######################################"
	@echo ""
	@echo "Docker management (run on host):"
	@echo ""
	@grep -E '^docker-[a-zA-Z_0-9-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-28s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Development (run in container or local):"
	@echo ""
	@grep -E '^[a-zA-Z_0-9-]+:.*?## .*$$' $(MAKEFILE_LIST) | grep -v '^docker-' | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-28s\033[0m %s\n", $$1, $$2}'
	@echo ""

# Docker management (run on host)
docker-build: ## Build the development Docker image.
	docker build -t designconversations-dev .

docker-shell: ## Start interactive shell in container.
	docker run -it --rm -v "$(PWD):/workspace" -p 4000:4000 designconversations-dev bash

# Development targets (run in container or local dev environment)
install: ## Install Ruby and PHP dependencies.
	bundle install
	composer install --working-dir=src

build: ## Build Jekyll site.
	bundle exec jekyll build --config _config.yml

build-drafts: ## Build Jekyll site including drafts.
	bundle exec jekyll build --config _config.yml --drafts

serve: ## Serve Jekyll site locally.
	bundle exec jekyll serve --config _config.yml --host 0.0.0.0

serve-drafts: ## Serve Jekyll site locally including drafts.
	bundle exec jekyll serve --config _config.yml --drafts --host 0.0.0.0

# Episode processing workflow (run in sequence)
01-build-episodes: ## Build episode posts from Airtable (skips published).
	php src/01-build-episode-posts.php

01-build-episodes-force: ## Build episode posts from Airtable (all episodes).
	php src/01-build-episode-posts.php --force

02-encode-mp3s: ## Encode MP3s with lame (skips published).
	php src/02-encode-mp3s.php

02-encode-mp3s-force: ## Encode MP3s with lame (all episodes).
	php src/02-encode-mp3s.php --force

03-tag-mp3s: ## Tag MP3s with eyeD3 (skips published).
	php src/03-tag-mp3s.php

03-tag-mp3s-force: ## Tag MP3s with eyeD3 (all episodes).
	php src/03-tag-mp3s.php --force

04-upload-mp3s: ## Upload MP3s to Internet Archive (skips published).
	php src/04-upload-mp3s.php

04-upload-mp3s-force: ## Upload MP3s to Internet Archive (all episodes).
	php src/04-upload-mp3s.php --force
