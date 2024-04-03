#!/usr/bin/env perl

use strict;
use warnings;

our (\$ldap_uris, \$user_basedn, \$group_basedn, \$access_group_dn, \$admin_group_dn);

\$ldap_uris       = [qw(${ldap_uri})];
\$user_basedn     = '${accounts_basedn}';
\$group_basedn    = '${groups_basedn}';
\$admin_group_dn  = 'cn=${gitolite_admin_role},${roles_basedn}';
\$access_group_dn = 'cn=${gitolite_access_role},${roles_basedn}';
