define php_run
	cd ~/Dev/docker-lamp/;  docker compose exec php sh -c "cd wp-kama.dev; $1"
endef

php.connect:
	$(call php_run, cd public_html/wp-content/plugins/democracy-poll; bash)

phpunit:
	$(call php_run, cd public_html/wp-content/plugins/democracy-poll; composer run phpunit)
phpunit.xdebug:
	$(call php_run, cd public_html/wp-content/plugins/democracy-poll; composer run phpunit.xdebug)

composer.install:
	$(call php_run, cd public_html/wp-content/plugins/democracy-poll; composer install)
composer.update:
	$(call php_run, cd public_html/wp-content/plugins/democracy-poll; composer update)


define node_run
	docker run $(1) --rm -it  --name DEMOCRACY_node  --user node  -v ./:/usr/src/app  node:24-alpine  sh -c "cd /usr/src/app ; $(2)"
endef

node.connect:
	$(call node_run,, sh)

npm.install:
	$(call node_run,, npm install)
npm.update:
	$(call node_run,, npm update)

npm.watch:
	$(call node_run,, npm run watch)
npm.build:
	$(call node_run,, npm run build)

docs.install:
	$(call node_run,, npm --prefix docs install)

docs.build:
	$(call node_run,, npm --prefix docs run build)

docs.dev:
	$(call node_run, -p 127.0.0.1:5173:5173, npm --prefix docs run dev -- --host 0.0.0.0)
	@echo "See: http://localhost:5173/democracy-poll/"

#########################################################
#                       i18n                            #
#########################################################

LANGUAGES_DIR := public_html/wp-content/plugins/democracy-poll/languages

i18n.update-po:
	bash languages/make-pot.sh
	$(call php_run, wp i18n update-po  "$(LANGUAGES_DIR)/democracy-poll.pot")

i18n.make-mo-php:
	$(call php_run, wp i18n make-mo   "$(LANGUAGES_DIR)"  "$(LANGUAGES_DIR)/build")
	$(call php_run, wp i18n make-php  "$(LANGUAGES_DIR)"  "$(LANGUAGES_DIR)/build")
