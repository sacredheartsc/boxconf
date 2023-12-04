bind_socket = "0.0.0.0:${rspamd_controller_port}";

password = "${rspamd_controller_password_hash}";
enable_password = "${rspamd_controller_password_hash}";

keypair {
  algorithm = "curve25519";
  privkey = "${rspamd_controller_privkey}";
  type = "kex";
  encoding = "base32";
  pubkey = "${rspamd_controller_pubkey}";
}
