<?php
putenv('TTRSS_DB_TYPE=pgsql');
putenv('TTRSS_DB_HOST=${ttrss_dbhost}');
putenv('TTRSS_DB_USER=${ttrss_user}');
putenv('TTRSS_DB_NAME=${ttrss_dbname}');

putenv('TTRSS_SELF_URL_PATH=https://${ttrss_hostname}.${domain}/');
putenv('TTRSS_PHP_EXECUTABLE=/usr/local/bin/php');

putenv('TTRSS_SESSION_COOKIE_LIFETIME=604800');

putenv('TTRSS_SMTP_FROM_NAME=Tiny Tiny RSS');
putenv('TTRSS_SMTP_FROM_ADDRESS=ttrss-noreply@${domain}');

putenv('TTRSS_CHECK_FOR_UPDATES=false');
putenv('TTRSS_CHECK_FOR_PLUGIN_UPDATES=false');
putenv('TTRSS_ENABLE_PLUGIN_INSTALLER=false');
putenv('TTRSS_ENABLE_GZIP_OUTPUT=false');
putenv('TTRSS_PLUGINS=auth_idm');

putenv('TTRSS_LOG_DESTINATION=syslog');

putenv('TTRSS_AUTH_IDM_URI=${ldaps_uri}');
putenv('TTRSS_AUTH_IDM_STARTTLS=false');
putenv('TTRSS_AUTH_IDM_BASEDN=${people_basedn}');
putenv('TTRSS_AUTH_IDM_SCOPE=one');
putenv('TTRSS_AUTH_IDM_FILTER=(memberOf=cn=${ttrss_access_role},${roles_basedn})');
putenv('TTRSS_AUTH_IDM_ADMIN_FILTER=(memberOf=cn=${ttrss_admin_role},${roles_basedn})');
