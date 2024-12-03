# Generating SSL Certificates

The following steps can be used to regenerate the SSL certificates for use with MySQL.

_NOTE_: The **CommonName (CN)** for each certificate must be different from all others.

### Certificates Config

Some configuration values needed by the certficates:

	echo "basicConstraints=CA:TRUE" > ca_v3.cnf
	echo "basicConstraints=CA:FALSE" > cert_v3.cnf 

### Certificate Authority (CA)

Create a new 2048-bit CA key, certificate request, and remove RSA passphrase from CA key.

    openssl req \
      -newkey rsa:2048 \
      -sha256 \
      -nodes \
      -keyout ca-key.pem \
      -subj "/C=US/ST=Anywhere/L=MyCity/O=Percona/OU=TrainingDept/CN=MyCoolCA" \
      -out ca-req.pem && openssl rsa -in ca-key.pem -out ca-key.pem

Sign CA certificate with own key

    openssl x509 -req \
      -sha256 \
      -days 3650 \
      -extfile ca_v3.cnf \
      -set_serial 1 \
      -in ca-req.pem -signkey ca-key.pem -out ca.pem

### Server Certificate

Create a new 2048-bit server key, certificate request, and remove RSA passphrase from server key.

    openssl req \
      -newkey rsa:2048 \
      -sha256 \
      -nodes \
      -keyout server-key.pem \
      -subj "/C=US/ST=Anywhere/L=MyCity/O=Percona/OU=TrainingDept/CN=MyCoolServer" \
      -out server-req.pem && openssl rsa -in server-key.pem -out server-key.pem

Create and sign the server certificate

    openssl x509 -req \
      -sha256 \
      -days 3650 \
      -extfile cert_v3.cnf \
      -set_serial 2 \
      -in server-req.pem -CA ca.pem -CAkey ca-key.pem -out server-cert.pem

### Client Certificate

Create a new 2048-bit client key, certificate request, and remove RSA passphrase from client key.

    openssl req \
      -newkey rsa:2048 \
      -sha256 \
      -nodes \
      -keyout client-key.pem \
      -subj /C=US/ST=Anywhere/L=MyCity/O=Percona/OU=TrainingDept/CN=MyCoolClient \
      -out client-req.pem && openssl rsa -in client-key.pem -out client-key.pem

Create and sign the client certificate

    openssl x509 -req \
      -sha256 \
      -days 3650 \
      -extfile cert_v3.cnf \
      -set_serial 3 \
      -in client-req.pem -CA ca.pem -CAkey ca-key.pem -out client-cert.pem

### Verify certificates

    openssl verify -CAfile ca.pem server-cert.pem client-cert.pem
