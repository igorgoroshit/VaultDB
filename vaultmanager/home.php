<div class="contentpanel">
	  <div class="panel panel-info">
            <div class="panel-heading">
              <h3 class="panel-title">Documentation</h3>
            </div>
        <div class="panel-body">

			<h2>VaultDB user manual</h2>
			<hr/>
			
			<h3>A quick overview</h3>
			VaultDB acts as a key-value document store. VaultDB uses "vaults" to store "documents". These "vaults" are the SQL equivalent of a "table". Inside the "vaults", the "documents" are the equivalent of a "table row".
			The VaultDB document is basically a string. This string can be a simple string or a serialized object using JSON. Documents can be encrypted or not. Documents can be
			encrypted for a specific user, a group or multiple users and groups. Only the users or groups for which a document was encrypted can be read by those users or groups.
			When you encrypt a document for a user, you do not need to know that user's password. When you encrypt a document for a group, every users inside that group will be
			able to decrypt that document automatically. This is possible because VaultDB uses public key cryptography to encrypt documents. 
			<p/><br/>
			VaultDB has it's own users. 
			These users can be member of groups. If a document is encrypted for a group, and the user logged in is a member of that group, that user can decrypt the document.
			VaultDB's groups are internal group used to encrypt and decrypt documents, they are not there to replace your application's groups, should there be any. On the other hand,
			VaultDB's users should be integrated inside your application. VaultDB provides solid, bcrypt-based user authentication and should replace your current authentication mechanisms or at
			least be linked to your login system. VaultDB uses a special "session token" that should be kept inside your application's session. This "session token" should be used to maintain
			authentication to avoid having to keep the user's credentials around to keep the session alive.
			<p/><br/>
			VaultDB is designed to allow for encryption of documents for multiple recipients. This is ideal for web applications where multiple users must often be able to access other
			user's documents without knowing that user's password. For example, an administrator must be able to see a simple user's documents and that simple user must not be able to
			access an administrator's document. This can be achieved using VaultDB.
			<p/><br/>
			With this in mind, let's take a look at VaultDB more closely.
			
			<h3>First steps</h3>
			The first step to use VaultDB is to edit the "vaultdb.php" file to configure the MySQL database used for storage. VaultDB needs a MySQL database to store it's data. Open
			the "vaultdb.php" file and edit the first private array of the class:
<pre>
private $dbconfig = array(
	"hostname" => "localhost",
	"username" => "vaultdb",
	"password" => "vaultdb",
	"database" => "vaultdb",
	"port" => "3306"
);
</pre>
			
			The next step to load VaultDB is to include the "vaultdb.php" file inside your application. Once included, the VaultDB object must be instantiated like any other simple PHP class. 
			Note that VaultDB will automatically create the needed tables so you don't have to worry about that, you only need an empty database dedicated to VaultDB.
			
<pre>
require_once("vaultdb.php");

$vault = new VaultDB();
</pre>   
			Now that VaultDB has been loaded, let's take a look at vaults.
			
			<h3>Vaults</h3>
			
			VaultDB is a key-value document store. Vaults are where VaultDB stores it's documents. They are the equivalent of a SQL database table. Documents inside vaults are accessed by its keys. 
			Keys are what you would call a "primary key" inside a table. In VaultDB, keys are usually a hash. The "value" part of a document, is the data you need to store inside the document itself.
			It can be any string you like such as a serialized JSON object, the base64 representation of a file, etc. Let's start by creating a vault.
<pre>
$vuid = $vault->addvault("bucket");
</pre>
			Note that the "addvault()" method returns the Vault's unique identifiant (vuid) on success and "false" if the vault already exists. Once a vault is created, you must select it before
			you can use it. To use a vault, use the "select()" method to select it.
			
<pre>
$vault->select("bucket");
</pre>
			You are now using "bucket" to perform any further operations. If the vault does not exists, the "select()" method will return "false".
			Let's insert a simple document inside the "bucket" vault we have just created. For our first document, we will use "MY_FIRST_DOCUMENT" as the key and we will use
			a JSON representation of a simple array as the value.
			
<pre>
$my_array = json_encode(
	array(
		"name" => "John Doe",
		"age" => 32,
		"email" => "john.doe@nasa.gov"
	)
);

$vault->select("bucket");
$vault->set("MY_FIRST_DOCUMENT", $my_array);
</pre>
			Now that we have stored John Doe's information, we want to access it by using the "get()" method.
			
<pre>
$vault->select("bucket");
$my_array = $vault->get("MY_FIRST_DOCUMENT");

$my_array = json_decode($my_array);
</pre>
			Let's move on to users.
			
			<h3>Users</h3>
			
			Users plays an important role in VaultDB. The goal of VaultDB is to be able to store encrypted document in vaults. VaultDB uses a combination of symmetric and asymmetric encryption to secure documents.
			Because of this, instead of using an encryption key when using symmetric encryption or a public key when using asymmetric encryption, in VaultDB we use user unique identifiants (uuid) or group unique
			identifiant (guid). These users and groups have public and private keys associated with them. The symmetric encryption (AES) key used to encrypt the documents is encrypted using the public key of these
			users or groups, or both. This way, VaultDB will encrypt documents using symmetric encryption (AES) without knowing the AES key because it is protected by the asymmetric encryption (RSA). This is important
			to understand because it allows for multi recipient encryption, much like PGP, something that cannot be done using AES alone. Using uuid and guid to encrypt documents enables you to encrypt a document
			for multiple recipients without knowing the other recipients passwords, which is ideal in a web application.
			
			
			<p/><br/>
			VaultDB users have passwords. These passwords are stored using bcrypt in the database dedicated to VaultDB. The user's password is used to decrypt that user's private key, which, in turn, can decrypt
			the documents encrypted with that user's public key. The key used to encrypt the user's private key is derived from the user's password using PBKDF2. The VaultDB user passwords should be the same
			used by your web application's real users. VaultDB users should be seen as an extension to your web application users.
			<p/><br/>
			So, to encrypt documents in VaultDB, you first need users. To create a user, simply use the "adduser()" method.
<pre>
$username = "admin";
$password = "admin123";

$uuid = $vault->adduser($username, $password);
</pre>
			Now a user named "admin" has been created along with it's public and private key. That user's uuid will be returned by the "adduser()" method. You should keep the uuid alongside the
			"admin" user in your web application, for references.
			
			<p/><br/>
			
			VaultDB provides a solid authentication mechanism, using bcrypt, that should replace your web application's authentication or at least be linked to it. When your user logs in your
			web application, instead of passing that user's password to your login function, you should pass that password to the "login()" method. Also, when reading encrypted documents,
			you need to use the "login()" method to establish a session with VaultDB.
			
			<p/><br/>
			
			The "login()" method enables you to do three things; it authenticate your user to your web application, it authenticate your user to VaultDB for reading encrypted documents and
			it creates a "session token" used to maintain the authentication within VaultDB and your web application. You can store that "session token" inside a cookie on the client side.
			The session token does not include the user's password so it's safe for cookies. The user's password is encrypted inside the MySQL database dedicated to VaultDB and is linked
			to the session token given by the "login()" method. Once you have logged in, you only need to pass the session token to the "auth()" method to keep the session alive and to keep
			the user logged in without having to store the user's password in the cookie. In other words, you do not need to keep the user's password once it's logged in, only the session
			token. Here is a simple login example.
			
<pre>

// Login (from a POST based login form)

$username = $_POST["username"];
$password = $_POST["password"];

$session_token = $vault->login($username, $password);

if ($session_token === false) {
	echo 'ERROR: Invalid user/password.';
	die();
} else {
	session_start();
	$_SESSION["session_token"] = $session_token;
}
</pre>
			Now the user is logged in and the session is established. Once that is done, the next page can simply load the session token with the "auth()" method to authenticate the session.
<pre>
$session_token = $_SESSION["session_token"];

if ($vault->auth($session_token) === false) {
	echo 'You have been disconnected, please login again.';
	die();
}

echo 'You are authorized to continue.';

// Do stuff here

</pre>
			This session token is safe to use in a cookie and will expire in 1 hour, if there is no activity. Now you can read encrypted documents. Let's take a look at encryption.
			
			<h3>Encryption</h3>
			
			To encrypt a document you need two things; a document and a unique identifier. Let's encrypt a simple document for the "admin" user inside the "bucket" vault we created earlier. 
			You can use the "getuuid()" method to get the uuid of a user.
<pre>
$session_token = $_SESSION["session_token"];

if ($vault->auth($session_token) === false) {
	echo 'You have been disconnected, please login again.';
	die();
}

$my_array = json_encode(
	array(
		"name" => "John Doe",
		"age" => 32,
		"email" => "john.doe@nasa.gov"
	)
);

$uuid = $vault->getuuid("admin");

$vault->select("bucket");
$vault->set("MY_ENCRYPTED_DOCUMENT", $my_array, $uuid);

</pre>
			Wow, that was easy! Now the "MY_ENCRYPTED_DOCUMENT" document can only be read by the "admin" user. Let's do that.
<pre>
$session_token = $_SESSION["session_token"];

if ($vault->auth($session_token) === false) {
	echo 'You have been disconnected, please login again.';
	die();
}


$vault->select("bucket");

$my_array = $vault->get("MY_ENCRYPTED_DOCUMENT");

$my_array = json_decode($my_array);

</pre>
			$my_array should now contain the document. In order to decrypt document, you must be authenticated with a user specified when you encrypted the document.
			
			<p/><br/>
			
			Let's try to encrypt a document for multiple recipients. To do that, you simply need to pass the user's uuid you wish to be able to read the document in an array to the "set()" method.
			
<pre>
$my_array = json_encode(
	array(
		"name" => "John Doe",
		"age" => 32,
		"email" => "john.doe@nasa.gov"
	)
);


$admin_uuid = $vault->getuuid("admin");
$bob_uuid = $vault->getuuid("bob");

$vault->select("bucket");
$vault->set("MY_ENCRYPTED_DOCUMENT", $my_array, array($admin_uuid, $bob_uuid));
</pre>
			Now both "admin" and "bob" can read the document. Let's try a more complex setup and check out groups.
			
			<h3>Groups</h3>
			
			Groups are useful if you want to encrypt a document for multiple users with a single guid. Because of the way group works, a group can never be empty. There must always
			be at least one member to a group. In order to add a new member to a group, a user, who is already a member of that group, must be logged in and authenticated. In other words
			groups are invitation only. This is important because if you are not a member of a group you can't add new users to that group. 
			
			To create a group, you must assign a user to it. That user will then be able to add new members to that group. Let's give it a shot.
			
<pre>

$uuid = $vault->getuuid("admin");

$guid = $vault->addgroup("administrators", $uuid);

</pre>
			Now let's add "bob" to that group, using the "groupaddmember()" method.
<pre>
$session_token = $_SESSION["session_token"]; 

if ($vault->auth($session_token) === false) {
	echo 'You have been disconnected, please login again.';
	die();
}

$bob_uuid = $vault->getuuid("bob");

$vault->groupaddmember("administrators", $bob_uuid);
</pre>
			So, any document encrypted using that group guid will be accessible for both "admin" and "bob". To encrypt a document for a group, just pass the guid to the "set()" method.
			
<pre>

$my_array = json_encode(
	array(
		"name" => "John Doe",
		"age" => 32,
		"email" => "john.doe@nasa.gov"
	)
);

$guid = $vault->getguid("administrators");

$vault->select("bucket");
$vault->set("MY_ENCRYPTED_DOCUMENT", $my_array, $guid);
</pre>
			"bob" and "admin" can now decrypt "MY_ENCRYPTED_DOCUMENT" on their own, if they are authenticated. For web applications, this is useful because now I can create a group for my standard users, a group
			for my administrators, and the administrators can read the user's documents. If a user looses his/her password, an administrator can still read that user's documents. And a document can be encrypted
			only for the administrators group, keeping the standard users from reading the administrators documents.
			
			<hr>
			You can search for documents using patterns. You can search for encrypted documents as long as you are allowed to read the document.
			
			check for PKDF
			check for bcrypt
        </div>
      </div>
</div>