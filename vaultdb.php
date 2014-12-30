<?php

/*

VaultDB
Copyright (c) 2012-2014, Maxime Labelle
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. All advertising materials mentioning features or use of this software
   must display the following acknowledgement:
   This product includes software developed by Maxime Labelle.
4. Neither the name of VaultDB nor the
   names of its contributors may be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY MAXIME LABELLE ''AS IS'' AND ANY
EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL MAXIME LABELLE BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

class VaultDB {

	//////////////////////////////////////

	private $dbconfig = array(
		"hostname" => "localhost",
		"username" => "vaultdb",
		"password" => "vaultdb",
		"database" => "vaultdb",
		"port" => "3306"
	);
	
	// The RSAKeySize must be a multiple of 256
	// Do not use a RSAKeySize lower than 2048 bits

	private $RSAKeySize = 4096;

	//////////////////////////////////////

	private $db = false;
	private $currentvuid = false;
	private $session_token = false;

	function __construct() {
		if ($this->checkconfig() === false) { 
			die("Error checking configuration.");
		}
		if ($this->init() === false) {
			die("Unable to connect to database, check config.");
		}

		if ($this->RSAKeySize < 2048) { 
			die("Invalid RSAKeySize, do not use a key size smaller than 2048 bits.");
		}

		if ($this->RSAKeySize % 256 != 0) {
			die("Invalid RSAKeySize, must be a multiple of 256.");
		}

		// Session garbage collector
		$this->db->query("DELETE FROM sessions WHERE sessiontime <= '".strtotime("-1 hour")."'");

		// document garbage collector
		$this->db->query("DELETE FROM documents WHERE expiration<='".time()."' AND expiration!='0'");
	}

	function __destruct() {
		if ($this->db !== false) {
			$this->db->close();
		}
	}

	public function checkconfig() {
		if (is_array($this->dbconfig)) {
			if (
				!isset($this->dbconfig["hostname"]) ||
				!isset($this->dbconfig["username"]) ||
				!isset($this->dbconfig["password"]) ||
				!isset($this->dbconfig["database"]) ||
				!isset($this->dbconfig["port"])
			) { return false; }
		} else { return false; }
	}

	public function init() {
		$this->db = new mysqli($this->dbconfig["hostname"], $this->dbconfig["username"], $this->dbconfig["password"], $this->dbconfig["database"]);
		
		if ($this->db->connect_error) { return false; }
		
		if ($this->db->query("SELECT 1 FROM vaults") === false) {
			$this->db->query('CREATE TABLE IF NOT EXISTS `vaults` ( `id` text NOT NULL, `name` text NOT NULL);');
		}
		if ($this->db->query("SELECT 1 FROM documents") === false) {
			$this->db->query('CREATE TABLE IF NOT EXISTS `documents` ( `id` text NOT NULL, `vault_id` text NOT NULL, `document_key` varchar(1024) NOT NULL, `payload` longblob NOT NULL, `expiration` int(11) NOT NULL, KEY `document_key` (`document_key`(767)))');
		}
		if ($this->db->query("SELECT 1 FROM users") === false) {
			$this->db->query('CREATE TABLE IF NOT EXISTS `users` ( `id` text NOT NULL, `username` text NOT NULL, `passwordhash` text NOT NULL, `publickey` text NOT NULL, `privatekey` text NOT NULL, `groups` text NOT NULL)');
		}
		if ($this->db->query("SELECT 1 FROM groups") === false) {
			$this->db->query('CREATE TABLE IF NOT EXISTS `groups` ( `id` text NOT NULL, `groupname` text NOT NULL, `privatekey` text NOT NULL, `publickey` text NOT NULL, `members` text NOT NULL)');
		}
		if ($this->db->query("SELECT 1 FROM sessions") === false) {
			$this->db->query('CREATE TABLE IF NOT EXISTS `sessions` ( `id` text NOT NULL, `user_id` text NOT NULL, `sessiondata` text NOT NULL, `sessiontime` text NOT NULL)');
		}
	}

	public function login($username, $password) {
		$passwordhash = hash("sha512", $password);
		$query = $this->db->query("SELECT * FROM users WHERE username='$username'");
		if ($query->num_rows) {
			$row = $query->fetch_assoc();
			$hash = $row["passwordhash"];
			if ($hash == crypt($passwordhash, $hash)) {
				$uuid = $row["id"];
				$session_id = substr(hash("sha256", openssl_random_pseudo_bytes(64)), 0, 32);
				$session_key = substr(hash("sha256", openssl_random_pseudo_bytes(64)), 0, 32);
				$iv = openssl_random_pseudo_bytes(16);
				$sessiondata = base64_encode(json_encode(array(
					"iv" => base64_encode($iv),
					"passwordhash" => base64_encode(openssl_encrypt($passwordhash, "AES-256-CBC", $session_key, 0, $iv))
				)));

				$query = $this->db->query("SELECT * FROM sessions WHERE user_id='$uuid'");
				if ($query->num_rows) {
					$this->db->query("DELETE FROM sessions WHERE user_id='$uuid'");
				} 
				$this->db->query("INSERT INTO sessions(id, sessiondata, sessiontime, user_id) VALUES('$session_id','$sessiondata','".time()."', '$uuid')");	
				$this->session_token = $session_id.$session_key;

				return $this->session_token;
			} else { return false; }
		} else { return false; }
	}

	public function groupaddmember($groupname, $uuid) {
		$q=$this->db->query("SELECT * FROM groups WHERE groupname='$groupname'");
		if ($q->num_rows) {
			$r = $q->fetch_assoc();
			$members = json_decode(base64_decode($r["members"]), true);
			
			$memberprivatekey = $this->getprivkey($this->sessionuuid(), $this->sessionhash());	
			openssl_private_decrypt(base64_decode($members[$this->sessionuuid()]), $GROUPKey, $memberprivatekey, OPENSSL_PKCS1_OAEP_PADDING);
			
			$memberpublicKey = $this->getpubkey($uuid);
			openssl_public_encrypt($GROUPKey, $membergroupkey, $memberpublicKey, OPENSSL_PKCS1_OAEP_PADDING);

			$members[$uuid] = base64_encode($membergroupkey);
			$members = base64_encode(json_encode($members));
			$this->db->query("UPDATE groups SET members='$members' WHERE groupname='$groupname'");
			return true;
			
		} else { return false; }
	}
	
	public function groupremovemember($groupname, $uuid) {
		$q=$this->db->query("SELECT * FROM groups WHERE groupname='$groupname'");
		if ($q->num_rows) {
			$r = $q->fetch_assoc();
			$members = json_decode(base64_decode($r["members"]), true);
			unset($members[$uuid]);
			$members = base64_encode(json_encode($members));
			$this->db->query("UPDATE groups SET members='$members' WHERE groupname='$groupname'");
			return true;
		} else { return false; }
	}
	
	public function delgroup($groupname) {
		$this->db->query("DELETE FROM groups WHERE groupname='$groupname'");
		return true;
	}

	public function addgroup($groupname, $memberuuid) {
		$q=$this->db->query("SELECT * FROM groups WHERE groupname='$groupname'");
		if ($q->num_rows == 0) {
			$id = $this->tid();

			$GROUPKey = openssl_random_pseudo_bytes(32);

			$res = openssl_pkey_new(array(
						"digest_alg" => "sha512",
						"private_key_bits" => $this->RSAKeySize,
						"private_key_type" => OPENSSL_KEYTYPE_RSA,
						));

			openssl_pkey_export($res, $privatekey);

			$iv = openssl_random_pseudo_bytes(16);

			$privatekey = base64_encode(json_encode(array("iv"=>base64_encode($iv), "key" => base64_encode(openssl_encrypt($privatekey, "AES-256-CBC", $GROUPKey, 0, $iv)))));

			$pubKey = openssl_pkey_get_details($res);
			$pubKey = base64_encode($pubKey["key"]);

			$publickey = $pubKey["key"];

			$memberpublicKey = $this->getpubkey($memberuuid);
			openssl_public_encrypt($GROUPKey, $membergroupkey, $memberpublicKey, OPENSSL_PKCS1_OAEP_PADDING);

			$members = base64_encode(json_encode(array(
				$memberuuid => base64_encode($membergroupkey)
			)));

			$this->db->query("INSERT INTO groups(id, groupname, publickey, privatekey, members) VALUES('$id','$groupname','$publickey','$privatekey','$members')");	
		}
	}

	public function auth($session_token) {
		if (strlen($session_id) == 64) {
			$this->session_token = $sesson_token;
			$session_id = substr($this->session_token, 0, 32);
			$query = $this->db->query("SELECT * FROM sessions WHERE id='$session_id'");
			if ($query->num_rows) {
				$this->db->query("UPDATE sessions SET sessiontime='".time()."' WHERE id='$session_id'");
				return true;
			} else { return false; }
		} else { return false; }
	}

	public function sessionhash() {
		if ($this->session_token === false) { return false; }
		$session_id = substr($this->session_token, 0, 32);
		$query = $this->db->query("SELECT * FROM sessions WHERE id='$session_id'");
		if ($query->num_rows) {
			$session_key = substr($this->session_token, 32);
			$row = $query->fetch_assoc();
			$sessiondata = json_decode(base64_decode($row["sessiondata"]), true);
			$iv = base64_decode($sessiondata["iv"]);
			$passwordhash = openssl_decrypt(base64_decode($sessiondata["passwordhash"]), "AES-256-CBC", $session_key, 0, $iv);
			return $passwordhash;
		} else { return false; }
	} 

	public function sessionuuid() {
		if ($this->session_token === false) { return false; }
		$session_id = substr($this->session_token, 0, 32);
		$query = $this->db->query("SELECT * FROM sessions WHERE id='$session_id'");
		if ($query->num_rows) {
			$row = $query->fetch_assoc();
			return $row["user_id"];
		} else { return false; }
	}

	public function addvault($name) {
		$query = $this->db->query("SELECT * FROM vaults WHERE name='$name'");
		if ($query->num_rows) { return false; }
		$id = $this->tid();
		$this->db->query("INSERT INTO vaults(id, name) VALUES('$id','$name')");
		return $id;		
	}

	public function dropvault($name) {
		$vault_id = $this->getvuid($name);
		if ($this->currentvuid == $vault_id) { $this->currentvuid = false; }
		$this->db->query("DELETE FROM vaults WHERE id='$vault_id'");
		$this->db->query("DELETE FROM documents WHERE vault_id='$vault_id'");
		return true;
	}

	public function select($vault) {
		$vuid = $this->getvuid($vault);
		if ($vuid === false) { return false; }
		$this->currentvuid = $vuid;
	}

	public function truncate($shelf) {
		if ($this->currentvuid === false) { return false; }
		$this->db->query("DELETE FROM documents WHERE vault_id='".$this->currentvuid."'");
		return true;
	}

	public function passwd($newpassword) {
		$uuid = $this->sessionuuid();
		$passwordhash = $this->sessionhash();
		$privatekey = $this->getprivkey($uuid, $passwordhash);
		$iv = openssl_random_pseudo_bytes(16);
		$passwordhash = hash("sha512", $newpassword);
		$privKey = base64_encode(json_encode(array("iv"=>base64_encode($iv), "key" => base64_encode(openssl_encrypt($privatekey, "AES-256-CBC", $passwordhash, 0, $iv)))));
		$passwordhash = crypt($passwordhash, '$2y$12$'.bin2hex(openssl_random_pseudo_bytes(22)));
		$this->db->query("UPDATE users SET privatekey='$privKey', passwordhash='$passwordhash' WHERE id='$uuid'");
		return true;
	}

	public function expire($key, $expiration) {
		if ($this->currentvuid === false) { return false; }
		$expiration = strtotime("+$expiration seconds");
		$this->db->query("UPDATE documents SET expiration='$expiration' WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		return true;
	}

	public function persist($key) {
		if ($this->currentvuid === false) { return false; }
		$this->db->query("UPDATE documents SET expiration='0' WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		return true;
	}

	public function dropuser($uuid) {
		$this->db->query("DELETE FROM users WHERE id='$uuid'");
		return true;
	}

	public function exists($key) {
		if ($this->currentvuid === false) { return false; }
		$query = $this->db->query("SELECT * FROM documents WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		if ($query->num_rows) { return true; }
		else { return false; }
	}

	public function genkeys() {
		$uuid = $this->sessionuuid();
		$passwordhash = $this->sessionhash();
		$old_privatekey = $this->getprivkey($uuid, $passwordhash);

		$res = openssl_pkey_new(array(
					"digest_alg" => "sha512",
					"private_key_bits" => $this->RSAKeySize,
					"private_key_type" => OPENSSL_KEYTYPE_RSA,
					));

		openssl_pkey_export($res, $privatekey);

		$iv = openssl_random_pseudo_bytes(16);

		$privKey = base64_encode(json_encode(array("iv"=>base64_encode($iv), "key" => base64_encode(openssl_encrypt($privatekey, "AES-256-CBC", $passwordhash, 0, $iv)))));

		$pubKey = openssl_pkey_get_details($res);
		$pubKey = base64_encode($pubKey["key"]);

		$publickey = $pubKey["key"];

		$this->db->query("UPDATE users SET privatekey='$privKey',publickey='$pubKey' WHERE id='$uuid'");

		$query = $this->db->query("SELECT * FROM documents");
		while ( $row = $query->fetch_assoc() ) {
			$payload = json_decode(base64_decode($row["payload"]), true);
			$value = base64_decode($payload["document_value"]);
			$enveloppes = json_decode(base64_decode($payload["env"]), true);
			if ($payload["iv"] !== false) {
				$iv = base64_decode($payload["iv"]);
				$AESKey = false;
				if (isset($enveloppes[$uuid])) {
					$AESKey = false;
					openssl_private_decrypt(base64_decode($enveloppes[$uuid]), $AESKey, $old_privatekey, OPENSSL_PKCS1_OAEP_PADDING);
					openssl_public_encrypt($AESKey, $enveloppes[$uuid], $publickey, OPENSSL_PKCS1_OAEP_PADDING);
					$enveloppes[$uuid] = base64_encode($enveloppes[$uuid]);

					$payload["env"] = base64_encode(json_encode($enveloppes));
					$payload = base64_encode(json_encode($payload));
					$this->db->query("UPDATE documents SET payload='$payload' WHERE id='".$row["id"]."'");
				}
			}
		}
	}

	public function move($key, $newvault) {
		if ($this->currentvuid === false) { return false; }
		$newvault = $this->getvuid($newvault);
		$this->db->query("UPDATE documents SET vault_id='$newvault' WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		return true;
	}

	public function rekey($key, $newkey) {
		if ($this->currentvuid === false) { return false; }
		$this->db->query("UPDATE documents SET document_key='$newkey' WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		return true;
	}

	public function find($search) {
		// Search for a specific value in a shelf
		if ($this->currentvuid === false) { return false; }

		$keys = array();
		$query = $this->db->query("SELECT * FROM documents WHERE vault_id='".$this->currentvuid."'");
		while ( $row = $query->fetch_assoc() ) {
			$include = false;
			$payload = json_decode(base64_decode($row["payload"]), true);
			$value = base64_decode($payload["document_value"]);

			$enveloppes = json_decode(base64_decode($payload["env"]), true);

			if ($payload["iv"] !== false) {
				$iv = base64_decode($payload["iv"]);
				$AESKey = false;
				$uuid = $this->sessionuuid();
				if (isset($enveloppes[$uuid])) {
					$passwordhash = $this->sessionhash();
					$privatekey = $this->getprivkey($uuid, $passwordhash);
					openssl_private_decrypt(base64_decode($enveloppes[$uuid]), $AESKey, $privatekey, OPENSSL_PKCS1_OAEP_PADDING);
					$value = openssl_decrypt($value, "AES-256-CBC", $AESKey, 0, $iv);
				} else { $value = false; }
			}

			if (substr($search, 0, 1) == "^") {
				if (strpos($value, substr($search, 1)) === 0) {
					$include = true;
				}
			} elseif (substr($search, 0, 1) == "$") {
				if (substr($value, -strlen(substr($search, 1))) === substr($search, 1)) {
					$include = true;
				}
			} elseif (substr($search, 0, 1) == "%") {
				if (strstr($value, substr($search, 1))) {
					$include = true;
				}	
			} elseif (substr($search, 0, 1) == ">") {
				if ($value > substr($search, 1)) {
					$include = true;
				}	
			} elseif (substr($search, 0, 2) == ">=") {
				if ($value >= substr($search, 2)) {
					$include = true;
				}	
			} elseif (substr($search, 0, 1) == "<") {
				if ($value < substr($search, 1)) {
					$include = true;
				}	
			} elseif (substr($search, 0, 2) == "<=") {
				if ($value <= substr($search, 2)) {
					$include = true;
				}	
			} elseif (substr($search, 0, 1) == "!") {
				if ($value != substr($search, 1)) {
					$include = true;
				}	
			} elseif (substr($search, 0, 1) == "=") {
				if ($value == substr($search, 1)) {
					$include = true;
				}
			}

			if ($include) { $keys[] = $row["document_key"]; }
		}
		return $keys;
	}

	public function del($key) {
		if ($this->currentvuid === false) { return false; }
		$this->db->query("DELETE FROM documents WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");	
		return true;
	}

	public function get($key) {
		if ($this->currentvuid === false) { return false; }

		$query = $this->db->query("SELECT * FROM documents WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		if ($query->num_rows) {
			$row = $query->fetch_assoc;
			$payload = json_decode(base64_decode($row["payload"]), true);

			$value = base64_decode($payload["document_value"]);

			$enveloppes = json_decode(base64_decode($payload["env"]), true);

			if ($payload["iv"] !== false) {
				$iv = base64_decode($payload["iv"]);
				$AESKey = false;
				$uuid = $this->sessionuuid();
				if (isset($enveloppes[$uuid])) {
					$passwordhash = $this->sessionhash();
					$privatekey = $this->getprivkey($uuid, $passwordhash);
					openssl_private_decrypt(base64_decode($enveloppes[$uuid]), $AESKey, $privatekey, OPENSSL_PKCS1_OAEP_PADDING);
					$value = openssl_decrypt($value, "AES-256-CBC", $AESKey, 0, $iv);
				} else { 
					$qg = $this->db->query("SELECT * FROM groups");
					while ( $rg = $qg->fetch_assoc() ) {
						$members = json_decode(base64_decode($rg["members"]), true);
						if (isset($members[$uuid])) {
							$guid = $rg["id"];
							$passwordhash = $this->sessionhash();
							$privatekey = $this->getprivkey($guid, $passwordhash);
							openssl_private_decrypt(base64_decode($enveloppes[$guid]), $AESKey, $privatekey, OPENSSL_PKCS1_OAEP_PADDING);
							$value = openssl_decrypt($value, "AES-256-CBC", $AESKey, 0, $iv);
							break;
						} else {
							$value = false; 
						}
					}
				}
			}

			return $value;
		} else { return false; }
	}

	public function keys() {
		// Search for keys in shelf
		// TODO: add string matching for keys
		if ($this->currentvuid === false) { return false; }

		$keys = array();
		$query = $this->db->query("SELECT document_key FROM documents WHERE vault_id='".$this->currentvuid."'");
		while ( $row = $query->fetch_assoc() ) {
			$keys[] = $row["document_key"];
		}
		return $keys;
	}

	public function set($key, $value, $recipients = false) {
		if ($this->currentvuid === false) { return false; }

		$AESKey = false;

		$enveloppes = array();

		$payload = array();

		if (count($recipients) && is_array($recipients)) {
			$AESKey = openssl_random_pseudo_bytes(32);
			foreach($recipients as $k => $recipient) {
				$publicKey = $this->getpubkey($recipient);
				openssl_public_encrypt($AESKey, $enveloppes[$recipient], $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
				$enveloppes[$recipient] = base64_encode($enveloppes[$recipient]);
			}

			$iv = openssl_random_pseudo_bytes(16);

			$payload["iv"] = base64_encode($iv);
			$payload["env"] = base64_encode(json_encode($enveloppes));
			$payload["document_value"] = base64_encode(openssl_encrypt($value, "AES-256-CBC", $AESKey, 0, $iv));
			$payload = base64_encode(json_encode($payload));
		} else {
			$payload = base64_encode(json_encode(array(
							"iv" => false,
							"env" => base64_encode(json_encode($enveloppes)),
							"document_value" => base64_encode($value)
							)));
		}

		$id = $this->tid();
		$query = $this->db->query("SELECT * FROM documents WHERE vault_id='".$this->currentvuid."' AND document_key='$key'");
		if ($query->num_rows) {
			$row = $query->fetch_assoc;
			$id = $row["id"];
			$this->db->query("UPDATE documents SET payload='$payload' WHERE id='$id'");
		} else {
			$this->db->query("INSERT INTO documents(id, vault_id, document_key, payload) VALUES('$id','".$this->currentvuid."','$key','$payload')");
		}
	}

	public function adduser($username, $password) {
		$query = $this->db->query("SELECT * FROM users WHERE username='$username'");
		if ($query->num_rows) { return false; }

		$res = openssl_pkey_new(array(
					"digest_alg" => "sha512",
					"private_key_bits" => $this->RSAKeySize,
					"private_key_type" => OPENSSL_KEYTYPE_RSA,
					));

		openssl_pkey_export($res, $privatekey);

		$iv = openssl_random_pseudo_bytes(16);

		$passwordhash = hash("sha512", $password);	

		$privKey = base64_encode(json_encode(array("iv"=>base64_encode($iv), "key" => base64_encode(openssl_encrypt($privatekey, "AES-256-CBC", $passwordhash, 0, $iv)))));

		$pubKey = openssl_pkey_get_details($res);
		$pubKey = base64_encode($pubKey["key"]);

		$id = $this->tid();

		$passwordhash = crypt($passwordhash, '$2y$12$'.bin2hex(openssl_random_pseudo_bytes(22)));

		$this->db->query("INSERT INTO users (id, username, passwordhash, privatekey, publickey) VALUES(
			'$id',
			'$username',
			'$passwordhash',
			'$privKey',
			'$pubKey'
				)");

		return $id;
	}

	public function deluser($username) {
		$this->db->query("DELETE FROM users WHERE username='$username'");
		return true;
	}

	public function getprivkey($uuid, $key) {
		$query = $this->db->query("SELECT * FROM users WHERE id='$uuid'");
		if ($query->num_rows) {
			$row = $query->fetch_assoc();
			$privKey = json_decode(base64_decode($row["privatekey"]), true);
			$iv = base64_decode($privKey["iv"]);
			return openssl_decrypt(base64_decode($privKey["key"]), "AES-256-CBC", $key, 0, $iv);
		} else { 
			$query = $this->db->query("SELECT * FROM groups WHERE id='$uuid'");
			if ($query->num_rows) {
				$row = $query->fetch_assoc();
				$privKey = json_decode(base64_decode($row["privatekey"]), true);
				$iv = base64_decode($privKey["iv"]);
				
				$memberprivatekey = $this->getprivkey($this->sessionuuid(), $key);
				$members = json_decode(base64_decode($row["members"]), true);
				
				openssl_private_decrypt(base64_decode($members[$this->sessionuuid()]), $GROUPKey, $memberprivatekey, OPENSSL_PKCS1_OAEP_PADDING);
				
				return openssl_decrypt(base64_decode($privKey["key"]), "AES-256-CBC", $GROUPkey, 0, $iv);
			} else { return false; }		
		}
	}

	public function getpubkey($uuid) {
		$query = $this->db->query("SELECT * FROM users WHERE id='$uuid'");
		if ($query->num_rows) {
			$row = $query->fetch_assoc();
			return base64_decode($row["publickey"]);
		} else { 
			$query = $this->db->query("SELECT * FROM groups WHERE id='$uuid'");
			if ($query->num_rows) {
				$row = $query->fetch_assoc();
				return base64_decode($row["publickey"]);
			} else { return false; }		
		}
	}

	public function getuuid($username) {
		$query = $this->db->query("SELECT * FROM users WHERE username='$username'");
		if ($query->num_rows) {
			$r = $query->fetch_assoc();
			return $r["id"];
		} else { return false; }
	}

	public function getvuid($vault) {
		$query = $this->db->query("SELECT * FROM vaults WHERE name='$vault'");
		if ($query->num_rows) {
			$r = $query->fetch_assoc();
			return $r["id"];
		} else { return false; }
	}

	public function tid() {
		return strtoupper(hash("sha256", microtime().uniqid()));
	}
}

?>
