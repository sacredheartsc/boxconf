Boxconf
=======
A config management framework based on POSIX sh(1).

Boxconf is an extremely simple config management system written in shell. As
long as you can SSH to the remote system (or "box") as root, the only
requirement is a POSIX-compliant sh(1) and coreutils.

This project was inspired by many years of frustration with Ansible. It's
currently a work in progress, and tailored to my personal FreeBSD infrastrcture.
If you like my approach, feel free to fork it for use in your own homelab!

Running Boxconf
---------------
To run boxconf on a target host, just run the following:

    ./boxconf $TARGET_HOSTNAME

The entirety of the `boxconf` directory will be tar'd up and SCP'd to the
remote box. After gathering some information about the target system (such as
operating system, IP address, etc), boxconf will source your scripts in the
following order:

    vars/common
    vars/os_family/${os_family}
    vars/os_distribution/${os_distribution}
    vars/hostclass/${hostclass}
    vars/hostname/${hostname}
    scripts/common
    scripts/os_family/${os_family}
    scripts/os_distribution/${os_distribution}
    scripts/hostclass/${hostclass}
    scripts/hostname/${hostname}

If any of those paths point to a directory, boxconf will source all files in
that directory in glob order.

The `hostname` value is taken from the short hostname of the remote system.
If the remote hostname is incorrect (or unset), you can override the hostname
detection by passing the `-o $OVERRIDE_HOSTNAME` flag to boxconf.

The `hostclass` value is matched based on the regular expressions listed in
the `hostclasses` file in the root of this directory.

Copying Files to the Remote Host
--------------------------------
You can copy files in the `files/` directory to the remote system using the
`install_file` function. The source file should have the same path as the
remote path, and can be tailored to the remote system by adding a custom
suffix. For example, if you ran the following code:

    install_file -m 0644 /etc/passwd

Then the following files would be tried (the first match wins):

    files/etc/etc/passwd.${hostname}
    files/etc/etc/passwd.${hostclass}.${os_distribution}
    files/etc/etc/passwd.${os_distribution}.${hostclass}
    files/etc/etc/passwd.${hostclass}.${os_family}
    files/etc/etc/passwd.${os_family}.${hostclass}
    files/etc/etc/passwd.${hostclass}
    files/etc/etc/passwd.${os_distribution}
    files/etc/etc/passwd.${os_family}
    files/etc/etc/passwd

If you use the `install_template` function, then the same file matching logic
applies. However, the content of the matched file will be treated like a
heredoc, allowing you to do things interpolate `${shell_variables}` and perform
`$(process_substitution)` within the file. Note that if you do this, then you
must esacape shell characters (like `\$`) as needed.

Encrypted Files
---------------
Finally, you also have the ability to encrypt any shell script or file via
OpenSSL's pbkdf2 (see `man openssl-enc`). Boxconf will automatically decrypt
the file before sourcing or copying to the remote system. The decryption
password is read from the `.vault_password` file or by prompting interactively.

The `vault` helper script in the root of this directory can be used to manage
encrypted files.

Copying TLS Certificates
------------------------
The `install_certificate` and `install_certificate_key` functions can be used
to copy a certificate with the given name to the remote host. The certificates
should be created and managed using the `pki` helper script in the root of
this directory.

Note that certificate keys are also encrypted with the `$VAULT_PASSWORD`, and
will be automatically decrypted when they are copied to the remote host.


Other Features
--------------
Check out the `lib/` directory for other miscellaneous functions that Boxconf
provides out-of-the box. However, it ain't much!

When I find myself writing the same logic over and and over for many different
types of hosts, I'll usually throw a function in there. But the "standard
library" is kept intentionally small. Just stick to POSIX tools.
