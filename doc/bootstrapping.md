Bootstrapping the Environment
=============================

All hosts that are built with `boxconf` depend on at least two other hosts
being available:

  1. The IDM (identity management) server. This server provides DNS, Kerberos
     authentication, and LDAP user and group lookups.

  2. The package repository. Almost all hosts are FreeBSD, and they depend on
     the local `poudriere` server which hosts in-house packages built with
     custom options.

The IDM servers and the package repo are themselves built with boxconf, but you
must build them in a specific order to solve the chicken-and-egg problem.


Step 0: The Hypervisor
----------------------

It is assumed that these hosts will be FreeBSD jails. Therefore, you will need
a FreeBSD "hypervisor" with our custom `jailctl` script.

Boxconf can be used the configure a FreeBSD hypervisor for running jails and
bhyve VMs. The only requirement is that you can SSH to this server as the root
user.

Boxconf assumes any host named `alcatraz[0-9]` has the `freebsd_hypervisor`
hostclass. Therefore, you can run the following to setup the jail host:

    ./boxconf -o alcatraz1 -e no_idm=true $FREEBSD_HYPERVISOR_IP


Step 1: The Pkg Repository
--------------------------

First, we'll need a jail to serve as our Poudriere server. This jail will build
all the necessary packages and serve them over HTTP.

On the FreeBSD hypervisor, use `jailctl` to create a jail for the `pkg` repo.
The following command will create a jail named `pkg1` with VLAN tag `199`,
IP address `10.11.199.4`, 32G memory limit, 256G disk quota, and 16 CPU cores:

    alcatraz1# jailctl create \
      -v 199                  \
      -a 10.11.199.4          \
      -g 10.11.199.1          \
      -n 255.255.255.0        \
      -r 8.8.8.8  -r 8.8.4.4  \
      -k ~/id_ed25519.pub     \
      -c 2-17                 \
      -m 32g                  \
      -q 256g                 \
      pkg1 freebsd13

Poudriere requires some special jail options. Run `jailctl edit pkg1` and set
the following options:

    mount.procfs          = true;
    allow.mount.tmpfs     = true;
    allow.mount.devfs     = true;
    allow.mount.procfs    = true;
    allow.mount.linprocfs = true;
    allow.mount.nullfs    = true;
    allow.raw_sockets     = true;
    allow.socket_af       = true;
    sysvmsg               = new;
    sysvsem               = new;
    sysvshm               = new;
    children.max          = 1000;

The restart the jail:

    alcatraz1# jailctl stop pkg1
    alcatraz1# jailctl start pkg1

Now you are ready to build all the packages and create the repository. Boxconf
assumes that any host named `pkg[0-1]` has the `pkg_repository` hostclass.

    ./boxconf -e no_idm=true 10.11.199.4

Substitute whatever IP you chose for the `pkg1` jail as necessary. Note that it
will take awhile to build all the packages for the first time.


Step 2: The IDM Servers
-----------------------

Next, we'll build the IDM jails. While you technically only need one, you should
build at least two so that you can reboot one of them without causing a DNS
outage for your entire environment.


## Create the Jails

Let's two jails named `idm1` and `idm2`. Note that Boxconf assumes any host
named `idm[0-9]` has the `idm_server` hostclass.

    alcatraz1# jailctl create \
      -v 199                  \
      -a 10.11.199.2          \
      -g 10.11.199.1          \
      -n 255.255.255.0        \
      -r 8.8.8.8  -r 8.8.4.4  \
      -k ~/id_ed25519.pub     \
      -c 18-19                \
      -m 4g                   \
      -q 32G                  \
      idm1 freebsd13

    alcatraz1# jailctl create \
      -v 199                  \
      -a 10.11.199.3          \
      -g 10.11.199.1          \
      -n 255.255.255.0        \
      -r 8.8.8.8  -r 8.8.4.4  \
      -k ~/id_ed25519.pub     \
      -c 20-21                \
      -m 4g                   \
      -q 32G                  \
      idm2 freebsd13


## Set Boxconf Variables

Before continuing, you'll need to tailor `vars/common/10-site` to your
environment:

  - The `domain` variable must contain your internal domain name. Eg:

        domain=idm.example.com

  - The `pkg_host_ipv4` variable must contain the IP address of the `pkg1` jail:

        pkg_host_ipv4=10.11.199.4

  - The `idm_servers` variable must contain a space-separated list of your IDM
    servers' short hostnames. Eg:

        idm_servers='idm1 idm2'

  - The `slapd_server_ids` variable must contain a newline-separated list of
    LDAP server IDs and their associated IP address. These should be the IPs of
    the jails you just created.

    The server ID can be any number 1-9, as long as it is unique for each host
    and you never, ever change it. Eg:

        slapd_server_ids="\
        1 10.11.199.2
        2 10.11.199.3"

  - The `reverse_dns_zones` variable must contain a space-separated list of
    all the reverse DNS zones in your environment. Eg:

        reverse_dns_zones="\
        199.11.10.in-addr.arpa
        200.11.10.in-addr.arpa"

    Note: only 3-octet IPv4 zones are supported (`10.in-addr.arpa` won't work).


## Create TLS Certificates

We will also need some TLS certificates for the LDAP servers. These certificates
allow for secure replication between the LDAP daemons on the IDM servers.

First, initialize the PKI. This will create a root certificate authority with
a name contraint for hostnames underneath your internal domain.

*However,* the LDAP servers replicate using their IP addresses, rather than DNS
names. Therefore, you will need to specify additional constraints for the IP
address of each IDM server. Eg:

    ./pki init                          \
      -c IP:10.11.199.2/255.255.255.255 \
      -c IP:10.11.199.3/255.255.255.255 \
      idm.example.com

Next, create server certificates for each IDM server. Each certificate will
need three SANs:

  - The FQDN of the IDM server.
  - The IP of the IDM server.
  - The bare domain name (we'll make this a multi-valued A record later).

Eg:

    ./pki cert -d 3650 idm1.idm.example.com IP:10.11.199.2 idm.example.com
    ./pki cert -d 3650 idm2.idm.example.com IP:10.11.199.3 idm.example.com

Finally, create a client certificate for the OpenLDAP replicator DN. Eg:

    ./pki client-cert -d 3650 cn=replicator,dc=idm,dc=example,dc=com


## Configure the IDM servers.

Now, you're ready to build the IDM servers!

The first server in the `$slapd_server_ids` list is somewhat special, as the
Boxconf scripts will use that one to create all the initial LDAP objects. 
So make sure you configure that one first.

    ./boxconf 10.11.199.2
    ./boxconf 10.11.199.3


## Verify LDAP replication, DNS, etc.

If everything is working, you should get the same result from each of the
following `dig` queries:

    $ dig +short @10.11.199.2 idm.example.com
    10.11.199.3
    10.11.199.2

    $ dig +short @10.11.199.3 idm.example.com
    10.11.199.3
    10.11.199.2


Step 3: Finish Building the Pkg Host
------------------------------------

At this point, our original `pkg1` jail is successfully serving up packages,
but it's not actually joined to our IDM domain.

Re-run Boxconf on this host (without the `no_idm` override this time) to get
everything squared away:

    ./boxconf 10.11.199.4
