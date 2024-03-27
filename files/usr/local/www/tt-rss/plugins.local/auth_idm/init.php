<?php
/**
 * The following options may be specified in config.php:
 *
 *   putenv('TTRSS_AUTH_IDM_URI=ldap://ldap.idm.example.com');
 *   putenv('TTRSS_AUTH_IDM_BASEDN=ou=users,dc=idm,dc=example,dc=com');
 *   putenv('TTRSS_AUTH_IDM_FILTER=(memberOf=cn=ttrss-users,ou=groups,dc=idm,dc=example,dc=com)');
 *   putenv('TTRSS_AUTH_IDM_ADMIN_FILTER=(memberOf=cn=ttrss-admins,ou=groups,dc=idm,dc=example,dc=com)');
 *   putenv('TTRSS_AUTH_IDM_FULLNAME_ATTRIBUTE=cn');
 *   putenv('TTRSS_AUTH_IDM_EMAIL_ATTRIBUTE=mail');
 */

class Auth_Idm extends Auth_Base {

  const AUTH_IDM_URI           = 'AUTH_IDM_URI';
  const AUTH_IDM_STARTTLS      = 'AUTH_IDM_STARTTLS';
  const AUTH_IDM_BASEDN        = 'AUTH_IDM_BASEDN';
  const AUTH_IDM_SCOPE         = 'AUTH_IDM_SCOPE';
  const AUTH_IDM_FILTER        = 'AUTH_IDM_FILTER';
  const AUTH_IDM_ADMIN_FILTER  = 'AUTH_IDM_ADMIN_FILTER';
  const AUTH_IDM_USERNAME_ATTR = 'AUTH_IDM_USERNAME_ATTRIBUTE';
  const AUTH_IDM_FULLNAME_ATTR = 'AUTH_IDM_FULLNAME_ATTRIBUTE';
  const AUTH_IDM_EMAIL_ATTR    = 'AUTH_IDM_EMAIL_ATTRIBUTE';

  function about() {
    return array(null,
      'Authenticates against REMOTE_USER variable and LDAP server',
      'stonewall@sacredheartsc.com',
      true);
  }

  function init($host) {
    $host->add_hook($host::HOOK_AUTH_USER, $this);

    Config::add(self::AUTH_IDM_URI,           '',            Config::T_STRING);
    Config::add(self::AUTH_IDM_STARTTLS,      false,         Config::T_BOOL);
    Config::add(self::AUTH_IDM_BASEDN,        '',            Config::T_STRING);
    Config::add(self::AUTH_IDM_SCOPE,         'sub',         Config::T_STRING);
    Config::add(self::AUTH_IDM_FILTER,        '',            Config::T_STRING);
    Config::add(self::AUTH_IDM_ADMIN_FILTER,  '',            Config::T_STRING);
    Config::add(self::AUTH_IDM_USERNAME_ATTR, 'uid',         Config::T_STRING);
    Config::add(self::AUTH_IDM_FULLNAME_ATTR, 'cn',          Config::T_STRING);
    Config::add(self::AUTH_IDM_EMAIL_ATTR,    'mail',        Config::T_STRING);
  }

  private function ldap_get_user($username, $filter = null) {
    switch ($this->scope) {
    case 'sub':
      $searchfunc = 'ldap_search'; break;
    case 'one':
      $searchfunc = 'ldap_list'; break;
    case 'base':
      $searchfunc = 'ldap_read'; break;
    default:
      Logger::log(E_USER_ERROR, "auth_idm: invalid search scope: $scope");
      return null;
    }

    $uid_filter = '('
      . ldap_escape($this->username_attr, '', LDAP_ESCAPE_FILTER)
      . '='
      . ldap_escape($username, '', LDAP_ESCAPE_FILTER)
      . ')';

    if (empty($filter)) {
      $filter = $uid_filter;
    } else {
      $filter = "(&$filter$uid_filter)";
    }

    $results = $searchfunc($this->conn, $this->basedn, $filter, [$this->fullname_attr, $this->email_attr]);
    if ($results && ldap_count_entries($this->conn, $results) == 1) {
      if ($entry = ldap_first_entry($this->conn, $results)) {
        if ($dn = ldap_get_dn($this->conn, $entry)) {
          if ($attrs = ldap_get_attributes($this->conn, $entry)) {
            return array(
              'dn'       => $dn,
              'email'    => $attrs[$this->email_attr][0],
              'fullname' => $attrs[$this->fullname_attr][0]
            );
          }
        }
      }
    }
    return null;
  }

  function authenticate($username = null, $password = null, $service = '') {
    $this->basedn        = Config::get(self::AUTH_IDM_BASEDN);
    $this->scope         = Config::get(self::AUTH_IDM_SCOPE);
    $this->username_attr = Config::get(self::AUTH_IDM_USERNAME_ATTR);
    $this->fullname_attr = Config::get(self::AUTH_IDM_FULLNAME_ATTR);
    $this->email_attr    = Config::get(self::AUTH_IDM_EMAIL_ATTR);
    $uri                 = Config::get(self::AUTH_IDM_URI);
    $starttls            = Config::get(self::AUTH_IDM_STARTTLS);
    $filter              = Config::get(self::AUTH_IDM_FILTER);
    $admin_filter        = Config::get(self::AUTH_IDM_ADMIN_FILTER);

    // Get ldap connection handle.
    if (!$this->conn = ldap_connect($uri)) {
      return false;
    }

    // Set protocol version 3.
    if (!ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3)) {
      return false;
    }

    // Bind using kerberos credentials from the environment.
    if (!ldap_sasl_bind($this->conn, null, null, 'GSSAPI')) {
      return false;
    }

    // Initiate STARTTLS (if requested)
    if ($starttls and !ldap_start_tls($this->conn)) {
      return false;
    }

    // If REMOTE_USER was set by the webserver, use that.
    if (!empty($_SERVER['REMOTE_USER'])) {
      $username = $_SERVER['REMOTE_USER'];
    } elseif (empty($username)) {
      return false;
    }

    $is_admin = false;
    $user = null;

    // First, check if the ADIN_FILTER matches (if set).
    if (!empty($admin_filter)) {
      $user = $this->ldap_get_user($username, $admin_filter);
      isset($user) && $is_admin = true;
    }

    // If ADMIN_FILTER didn't match, try FILTER.
    if (!isset($user)) {
      $user = $this->ldap_get_user($username, $filter);
    }

    // If no matching user from LDAP, reject.
    if (!isset($user)) {
      return false;
    }

    // If webserver didn't validate the password, try an LDAP bind with the provided creds.
    if (empty($_SERVER['REMOTE_USER']) and !ldap_bind($this->conn, $user['dn'], $password)) {
      return false;
    }

    // Get the TTRSS internal user ID.
    if (!($userid = $this->auto_create_user($username))) {
      return false;
    }

    // Populate user details using the LDAP attributes.
    if (Config::get(Config::AUTH_AUTO_CREATE)) {
      if (!empty($user['fullname'])) {
        $sth = $this->pdo->prepare('UPDATE ttrss_users SET full_name = ? WHERE id = ?');
        $sth->execute([$user['fullname'], $userid]);
      }

      if (!empty($user['email'])) {
        $sth = $this->pdo->prepare('UPDATE ttrss_users SET email = ? WHERE id = ?');
        $sth->execute([$user['email'], $userid]);
      }

      $sth = $this->pdo->prepare('UPDATE ttrss_users SET access_level = ? WHERE id = ?');
      $sth->execute([$is_admin ? 10 : 0, $userid]);
    }

    return $userid;
  }

  function api_version() {
    return 2;
  }
}
