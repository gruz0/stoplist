.PHONY: help dockerize shell install_linters lint zip

PLUGIN_VERSION=$(shell grep -r ' \* Version:' stoplist.php | egrep -o '([0-9]{1,}\.)+[0-9]{1,}')

help:
	@echo 'Available targets:'
	@echo '  make dockerize'
	@echo '  make shell'
	@echo '  make install_linters'
	@echo '  make lint'
	@echo '  make zip'

dockerize:
	docker-compose down
	docker-compose up --build

shell:
	docker-compose exec wordpress bash

install_linters:
	bin/install_linters.sh

lint:
	bin/lint.sh

zip:
	bin/zip.sh $(PLUGIN_VERSION)
