define php_run
	cd ~/Dev/docker-lamp/;  docker compose exec php sh -c "cd wp-kama.dev; $1"
endef

#########################################################
#                       i18n                            #
#########################################################

LANGUAGES_DIR := public_html/wp-content/plugins/democracy-poll/languages

i18n_update_po:
	bash languages/make-pot.sh
	$(call php_run, wp i18n update-po  "$(LANGUAGES_DIR)/democracy-poll.pot")

i18n_make_mo_php:
	$(call php_run, wp i18n make-mo   "$(LANGUAGES_DIR)"  "$(LANGUAGES_DIR)/build")
	$(call php_run, wp i18n make-php  "$(LANGUAGES_DIR)"  "$(LANGUAGES_DIR)/build")
