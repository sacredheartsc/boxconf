#!/bin/sh

exec /usr/local/bin/rspamc             \\
  --connect="${rspamd_host}.${domain}" \\
  --password="${rspamd_rw_password}"   \\
  --key="${rspamd_pubkey}"             \\
  learn_spam
