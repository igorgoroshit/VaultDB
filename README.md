VaultDB
=======

VaultDB is a multi-recipient encrypted key-value store for LAMP-based web applications. 
It uses OpenSSL for encryption and MySQL as a storage engine. It uses a combination of
symmetric and asymmetric encryption to protect sensitive data inside documents. The goal
of VaultDB is to enable developers to have encrypted data without having to store 
encryption keys inside the PHP code itself, leaving them vulnerable. The use of public
key cryptography makes VaultDB ideal for web applications with multiple users because
the encryption can have multi-recipients. This allows a user with administrative
privileges to access data encrypted for a simple user without knowing that user's 
credentials.

