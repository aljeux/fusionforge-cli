DESTDIR=/usr/local

install:
	mkdir -p $(DESTDIR)/bin
	mkdir -p $(DESTDIR)/share/gforge
	cp gforge.php /usr/local/bin
	cp -r nusoap $(DESTDIR)/share/gforge
	cp -r include $(DESTDIR)/share/gforge 
	cat gforge.php | sed -e "s/define(\"NUSOAP_DIR\"[^)]*);/define(\"NUSOAP_DIR\", \"\/usr\/local\/share\/gforge\/nusoap\/lib\/\");/g" > /usr/local/bin/gforge.php
	cat gforge.php | sed -e "s/define(\"GFORGE_CLI_DIR\"[^)]*);/define(\"GFORGE_CLI_DIR\", \"\/usr\/local\/bin\/share\/gforge\/include\/\");/g" > /usr/local/bin/gforge.php

