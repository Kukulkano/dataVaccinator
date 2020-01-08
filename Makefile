base := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

all: makeinstaller

VERSION?=0.1.7

NAME=vaccinator
PHPINC=$(RF_INCLUDES)php/src

PHPFILES=$(PHPINC)/incCommon.php \
	$(PHPINC)/incDatabase.php

SOURCE_FOLDERS=$(base)/lib \
	$(base)/www
 
LIBDIR=$(base)/lib

getrev:
	$(eval REV := $(shell git describe --long `git log -1 --oneline -- $(base) | cut -d ' ' -f1` | sed ${RE} -e"s/rev-([0-9]+)-.*/\1/"))

showrev: getrev
	@echo $(REV)

$(LIBDIR)/common.php: $(base)/dist/common.php $(PHPFILES)
	cp $(base)/dist/common.php $@
	sed -f $(base)/dist/extract.sed $(PHPFILES) >> $@
	echo "\n?>" >> $@

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

makeinstaller: package $(LIBDIR)/common.php
	makeself $(NAME)-$(VERSION) $(NAME)-$(VERSION)-$(REV).sh \
		"$(NAME) $(VERSION)-$(REV)" ./install.sh

makedirs:
	mkdir -p $(NAME)-$(VERSION)/

clean:
	rm -fr $(NAME)-*
