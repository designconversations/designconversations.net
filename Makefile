build:
	bundle exec jekyll build --config _config.yml,_config_dev.yml

serve:
	bundle exec jekyll serve --config _config.yml

serve-dev:
	bundle exec jekyll serve --config _config.yml,_config_dev.yml

serve-dev-drafts:
	bundle exec jekyll serve --config _config.yml,_config_dev.yml --drafts
