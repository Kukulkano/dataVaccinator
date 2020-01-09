base := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

VERSION?=0.1.7
NAME=vaccinator

all: makeinstaller

$(eval _platform := $(shell uname))
ifeq "Darwin" "$(_platform)"
	RE=-E
else
	RE=-r
endif

SOURCE_FOLDERS=$(base)/lib \
	$(base)/www
 
LIBDIR=$(base)/lib

# use this file to implement local things
local_makefile.mk:
	touch $@

include local_makefile.mk

getrev:
	$(eval REV := $(shell git describe --long `git log -1 --oneline -- $(base) | cut -d ' ' -f1` | sed ${RE} -e"s/rev-([0-9]+)-.*/\1/"))

showrev: getrev
	@echo $(REV)

common: $(LIBDIR)/common.php

PKG=$(base)/$(NAME)-$(VERSION)
package: clean makedirs common getrev
	cp -fPr $(SOURCE_FOLDERS) $(PKG)/
	rm -f `find $(PKG)/ -name .gitignore`
	rm -f $(PKG)/lib/init.php $(PKG)/lib/version.php
	echo "<?php define('VACCINATOR_VERSION', '${VERSION}-${REV}'); ?>" \
		> $(PKG)/lib/version.php
	install -m 0755 $(base)/dist/install.sh $(PKG)/
	install -m 0644 $(base)/dist/init.php $(PKG)/

makeinstaller: package
	makeself $(NAME)-$(VERSION) $(NAME)-$(VERSION)-$(REV).sh \
		"$(NAME) $(VERSION)-$(REV)" ./install.sh

makedirs:
	mkdir -p $(NAME)-$(VERSION)/

clean:
	rm -fr $(NAME)-*
