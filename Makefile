build:
	bundle exec jekyll build --config _config.yml

build-drafts:
	bundle exec jekyll build --config _config.yml --drafts

#build-dev:
#	bundle exec jekyll build --config _config.yml,_config_dev.yml

#build-dev-drafts:
#	bundle exec jekyll build --config _config.yml,_config_dev.yml --drafts

serve:
	bundle exec jekyll serve --config _config.yml

serve-drafts:
	bundle exec jekyll serve --config _config.yml --drafts

#serve-dev:
#	bundle exec jekyll serve --config _config.yml,_config_dev.yml

#serve-dev-drafts:
#	bundle exec jekyll serve --config _config.yml,_config_dev.yml --drafts
