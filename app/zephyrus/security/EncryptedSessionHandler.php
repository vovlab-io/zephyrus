<?php namespace Zephyrus\Security;

use Zephyrus\Application\SessionHandler;

class EncryptedSessionHandler extends SessionHandler
{
    /**
     * @var string Encryption algorithm (mcrypt compatible)
     */
    private $encryptionAlgorithm = MCRYPT_RIJNDAEL_128;

    /**
     * @var int Encryption initiation vector size
     */
    private $cryptIvSize;

    /**
     * @var string Encryption symmetric key created using the specified mcrypt
     * algorithm in CBC mode.
     */
    private $cryptKey;

    /**
     * @var string HMac hash authentication key
     */
    private $cryptAuth;

    /**
     * @var string Cookie name that will store the encryption data (hmac and
     * symmetric key).
     */
    private $cookieKeyName;

    /**
     * Assigns session callback functions and make sure the mcrypt extension is
     * correctly installed.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (!extension_loaded('mcrypt')) {
            throw new \Exception(__CLASS__ . " needs the mcrypt PHP extension");
        }
        session_set_save_handler(
            [$this, "open"],
            [$this, "close"],
            [$this, "read"],
            [$this, "write"],
            [$this, "destroy"],
            [$this, "gc"]
        );
    }

    /**
     * Called on session_start, this method create the
     *
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     * @throws \Exception
     */
    public function open($savePath, $sessionName)
    {
        parent::open($savePath, $sessionName);
        $this->cookieKeyName = "key_$sessionName";
        $this->cryptIvSize = mcrypt_get_iv_size($this->encryptionAlgorithm, MCRYPT_MODE_CBC);

        if (empty($_COOKIE[$this->cookieKeyName]) || strpos($_COOKIE[$this->cookieKeyName], ':') === false) {
            $this->createEncryptionCookie();
        } else {
            list ($this->cryptKey, $this->cryptAuth) = explode(':', $_COOKIE[$this->cookieKeyName]);
            $this->cryptKey = base64_decode($this->cryptKey);
            $this->cryptAuth = base64_decode($this->cryptAuth);
        }
        return true;
    }

    /**
     * @throws \Exception
     */
    public function createEncryptionCookie()
    {
        $keyLength = mcrypt_get_key_size($this->encryptionAlgorithm, MCRYPT_MODE_CBC);
        $this->cryptKey = Cryptography::randomBytes($keyLength);
        $this->cryptAuth = Cryptography::randomBytes(32);

        $cookieSettings = session_get_cookie_params();
        setcookie(
            $this->cookieKeyName,
            base64_encode($this->cryptKey) . ':' . base64_encode($this->cryptAuth),
            ($cookieSettings['lifetime'] > 0)
                ? time() + $cookieSettings['lifetime']
                : 0,
            $cookieSettings['path'],
            $cookieSettings['domain'],
            $cookieSettings['secure'],
            $cookieSettings['httponly']
        );
    }

    /**
     * Decrypt data before storing it in the $_SESSION global.
     *
     * @param string $id
     * @return bool|string
     */
    public function read($id)
    {
        $data = parent::read($id);
        if ($data !== false) {
            return $this->decrypt($data);
        }
        return false;
    }

    /**
     * Encrypt data before writing it on disk.
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data)
    {
        return parent::write($id, $this->crypt($data));
    }

    /**
     * Destroy session file on disk and delete encryption cookie if no session
     * is active after deletion.
     *
     * @param string $id
     * @return bool
     */
    public function destroy($id)
    {
        parent::destroy($id);
        if (empty(session_id())) {
            if (isset($_COOKIE[$this->cookieKeyName])) {
                setcookie($this->cookieKeyName, '', 1);
                unset($_COOKIE[$this->cookieKeyName]);
            }
        }
        return true;
    }

    /**
     * Configure the PHP garbage collector deletion policy. Default is to
     * remove the session file when lifetime expires (about 24 minutes).
     *
     * @param int $lifetime
     * @return bool
     */
    public function gc($lifetime)
    {
        return parent::gc($lifetime);
    }

    /**
     * Applies an algorithm used to encrypt session data on disk. Specified
     * algorithm must be compatible with the Mcrypt extension.
     *
     * @param string $encryptionAlgorithm
     */
    public function setEncryptionAlgorithm($encryptionAlgorithm)
    {
        if (!in_array($encryptionAlgorithm, mcrypt_list_algorithms())) {
            throw new \InvalidArgumentException("The provided algorithm must be compatible with the PHP Mcrypt extension");
        }
        $this->encryptionAlgorithm = $encryptionAlgorithm;
    }

    /**
     * Encrypt the specified data using the defined algorithm in CBC mode. Also
     * create an Hmac authentication hash.
     *
     * @param $data
     * @return string
     */
    private function crypt($data)
    {
        $iv = mcrypt_create_iv($this->cryptIvSize, MCRYPT_DEV_URANDOM);
        $cipher = mcrypt_encrypt($this->encryptionAlgorithm,
                                 $this->cryptKey,
                                 $data,
                                 MCRYPT_MODE_CBC,
                                 $iv
        );
        $hmac = hash_hmac('sha256', $iv . $this->encryptionAlgorithm . $cipher, $this->cryptAuth);
        return $hmac . ':' . base64_encode($iv) . ':' . base64_encode($cipher);
    }

    /**
     * Decrypt the specified data using the defined algorithm in CBC mode. Also
     * verify the Hmac authentication hash. Returns false if Hmac validation
     * fails.
     *
     * @param $data
     * @return bool|string
     */
    private function decrypt($data)
    {
        list($hmac, $iv, $cipher) = explode(':', $data);
        $iv = base64_decode($iv);
        $cipher = base64_decode($cipher);
        $newHmac = hash_hmac('sha256', $iv . $this->encryptionAlgorithm . $cipher, $this->cryptAuth);
        if ($hmac !== $newHmac) {
            return false;
        }
        $decrypt = mcrypt_decrypt($this->encryptionAlgorithm,
                                  $this->cryptKey,
                                  $cipher,
                                  MCRYPT_MODE_CBC,
                                  $iv);
        return rtrim($decrypt, "\0");
    }
}