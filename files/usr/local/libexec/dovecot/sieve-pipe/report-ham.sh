#!/bin/sh

exec /usr/bin/rspamc                    \\
  --hostname="${rspamd_host}.${domain}" \\
  --password="${rspamd_rw_password}"    \\
  --key="${rspamd_pubkey}"              \\
  learn_ham
