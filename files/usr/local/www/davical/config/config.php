<?php
\$c->pg_connect[] = 'dbname=${davical_dbname} user=${davical_user} host=${postgres_host}.${domain}';
\$c->admin_email = '${davical_admin_email}';

\$c->restrict_setup_to_admin = true;

\$c->home_calendar_name = 'calendar';
\$c->home_addressbook_name = 'addressbook';
\$c->default_privileges = array('read-free-busy', 'schedule-deliver');
\$c->external_refresh = 60;
\$c->default_locale = 'en';
\$c->allow_get_email_visibility = true;

\$c->trust_x_forwarded = true;

\$c->authenticate_hook['call'] = 'LDAP_check';
\$c->authenticate_hook['config'] = array(
  'uri'             => '${ldap_uri}',
  'host'            => '${ldap_hosts}',
  'port'            => '389',
  'sasl'            => 'yes',
  'sasl_mech'       => 'GSSAPI',
  'baseDNUsers'     => '${people_basedn}',
  'baseDNGroups'    => '${groups_basedn}',
  'scope'           => 'onelevel',
  'protocolVersion' => 3,
  'optReferrals'    => 0,
  'filterUsers'     => '(&(objectclass=inetOrgPerson)(memberOf=cn=${dav_access_role},${roles_basedn}))',
  'filterGroups'    => '(objectclass=groupOfMembers)',
  'mapping_field' => array('username' => 'uid',
                           'modified' => 'modifyTimestamp',
                           'fullname' => 'cn',
                           'email'    => 'mailAddress'),
  'group_mapping_field' => array('name'     => 'cn',
                                 'fullname' => 'cn',
                                 'modified' => 'modifyTimestamp',
                                 'email'    => 'mailAddress',
                                 'members'  => 'member'),
  'group_member_dnfix' => true,
  'default_value' => array('date_format_type' => 'I','locale' => 'en'),
  'format_updated' => array('Y' => array(0,4),
                            'm' => array(4,2),
                            'd' => array(6,2),
                            'H' => array(8,2),
                            'M' => array(10,2),
                            'S' => array(12,2)),
  'i_use_mode_kerberos' => 'i_know_what_i_am_doing',
);
include_once('drivers_ldap.php');
