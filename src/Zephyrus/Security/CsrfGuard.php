<?php namespace Zephyrus\Security;

use Zephyrus\Application\Configuration;
use Zephyrus\Exceptions\InvalidCsrfException;
use Zephyrus\Network\Request;
use Zephyrus\Network\RequestFactory;

class CsrfGuard
{
    const HEADER_NAME = 'HTTP_X_CSRF_NAME';
    const HEADER_TOKEN = 'HTTP_X_CSRF_TOKEN';
    const REQUEST_TOKEN_NAME = 'CSRFName';
    const REQUEST_TOKEN_VALUE = 'CSRFToken';
    const TOKEN_LENGTH = 48;

    /**
     * Singleton pattern instance.
     *
     * @var CsrfGuard
     */
    private static $instance = null;

    /**
     * @var Request
     */
    private $request;

    /**
     * Determines if the HTTP GET requests are secured by the CSRF filter. It
     * implies that for EVERY request of this type, the CSRF token should be
     * provided.
     *
     * @var bool
     */
    private $getSecured = false;

    /**
     * Determines if the HTTP POST requests are secured by the CSRF filter. It
     * implies that for EVERY request of this type, the CSRF token should be
     * provided.
     *
     * @var bool
     */
    private $postSecured = true;

    /**
     * Determines if the HTTP PUT requests are secured by the CSRF filter. It
     * implies that for EVERY request of this type, the CSRF token should be
     * provided.
     *
     * @var bool
     */
    private $putSecured = true;

    /**
     * Determines if the HTTP DELETE requests are secured by the CSRF filter. It
     * implies that for EVERY request of this type, the CSRF token should be
     * provided.
     *
     * @var bool
     */
    private $deleteSecured = true;

    /**
     * @return CsrfGuard
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function kill()
    {
        self::$instance = null;
    }

    /**
     * Returns the corresponding HTML hidden fields for the CSRF.
     */
    public function generateHiddenFields()
    {
        $name = $this->generateFormName();
        $token = $this->generateToken($name);
        $html = '<input type="hidden" name="' . self::REQUEST_TOKEN_NAME . '" value="' . $name . '" />';
        $html .= '<input type="hidden" name="' . self::REQUEST_TOKEN_VALUE . '" value="' . $token . '" />';
        return $html;
    }

    /**
     * Proceeds to filter the current request for any CSRF mismatch. Forms must provide
     * its unique name and corresponding generated csrf token.
     *
     * @throws InvalidCsrfException
     */
    public function guard()
    {
        if ($this->isHttpMethodFiltered($this->request->getMethod())) {
            $formName = $this->getProvidedFormName();
            $providedToken = $this->getProvidedCsrfToken();
            if (is_null($formName) || is_null($providedToken)) {
                throw new InvalidCsrfException();
            }
            if (!$this->validateToken($formName, $providedToken)) {
                throw new InvalidCsrfException();
            }
        }
    }

    /**
     * Automatically adds CSRF hidden fields to any forms present in the given
     * HTML. This method is to be used with automatic injection behavior.
     *
     * @param string $html
     * @return string
     */
    public function injectForms($html)
    {
        preg_match_all("/<form(.*?)>(.*?)<\\/form>/is", $html, $matches, PREG_SET_ORDER);
        if (is_array($matches)) {
            foreach ($matches as $match) {
                if (strpos($match[1], "nocsrf") !== false) {
                    continue;
                }
                $hiddenFields = self::generateHiddenFields();
                $html = str_replace($match[0], "<form{$match[1]}>{$hiddenFields}{$match[2]}</form>", $html);
            }
        }
        return $html;
    }

    /**
     * @return boolean
     */
    public function isGetSecured()
    {
        return (bool) $this->getSecured;
    }

    /**
     * @param boolean $getSecured
     */
    public function setGetSecured($getSecured)
    {
        $this->getSecured = $getSecured;
    }

    /**
     * @return boolean
     */
    public function isPostSecured()
    {
        return (bool) $this->postSecured;
    }

    /**
     * @param boolean $postSecured
     */
    public function setPostSecured($postSecured)
    {
        $this->postSecured = $postSecured;
    }

    /**
     * @return boolean
     */
    public function isPutSecured()
    {
        return (bool) $this->putSecured;
    }

    /**
     * @param boolean $putSecured
     */
    public function setPutSecured($putSecured)
    {
        $this->putSecured = $putSecured;
    }

    /**
     * @return boolean
     */
    public function isDeleteSecured()
    {
        return (bool) $this->deleteSecured;
    }

    /**
     * @param boolean $deleteSecured
     */
    public function setDeleteSecured($deleteSecured)
    {
        $this->deleteSecured = $deleteSecured;
    }

    /**
     * Generates and stores in the current session a cryptographically random
     * token that shall be validated with the filter method.
     *
     * @param string $formName
     * @return string
     * @throws \Exception
     */
    private function generateToken($formName)
    {
        $token = Cryptography::randomString(self::TOKEN_LENGTH);
        if (!isset($_SESSION['__CSRF_TOKEN'])) {
            $_SESSION['__CSRF_TOKEN'] = [];
        }
        $_SESSION['__CSRF_TOKEN'][$formName] = $token;
        return $token;
    }

    /**
     * Returns a random name to be used for a form csrf token.
     *
     * @return string
     */
    private function generateFormName()
    {
        return "CSRFGuard_" . mt_rand(0, mt_getrandmax());
    }

    /**
     * Validates the given token with the one stored for the specified form
     * name. Once validated, good or not, the token is removed from the
     * session.
     *
     * @param $formName
     * @param $token
     * @return bool
     */
    private function validateToken($formName, $token)
    {
        $sortedCsrf = $this->getStoredCsrfToken($formName);
        if (!is_null($sortedCsrf)) {
            $_SESSION['__CSRF_TOKEN'][$formName] = '';
            unset($_SESSION['__CSRF_TOKEN'][$formName]);
            return hash_equals($sortedCsrf, $token);
        }
        return false;
    }

    /**
     * Obtains the CSRF token stored by the server for the corresponding
     * client. Returns null if undefined.
     *
     * @param string $formName
     * @return null|string
     */
    private function getStoredCsrfToken($formName)
    {
        return isset($_SESSION['__CSRF_TOKEN'][$formName]) ? $_SESSION['__CSRF_TOKEN'][$formName] : null;
    }

    /**
     * Obtains the CSRF token provided by the client either by request data
     * or by an HTTP header (e.g. Ajax based requests). Returns null if
     * undefined.
     *
     * @return null|string
     */
    private function getProvidedCsrfToken()
    {
        $token = $this->request->getParameter(self::REQUEST_TOKEN_VALUE);
        if (is_null($token)) {
            $token = isset($_SERVER[self::HEADER_TOKEN]) ? $_SERVER[self::HEADER_TOKEN] : null;
        }
        return $token;
    }

    /**
     * Obtains the form name provided by the client either by request data or
     * by an HTTP header (e.g. Ajax based requests). Returns null if undefined.
     *
     * @return null|string
     */
    private function getProvidedFormName()
    {
        $formName = $this->request->getParameter(self::REQUEST_TOKEN_NAME);
        if (is_null($formName)) {
            $formName = isset($_SERVER[self::HEADER_NAME]) ? $_SERVER[self::HEADER_NAME] : null;
        }
        return $formName;
    }

    /**
     * Checks if the specified method should be filtered.
     *
     * @param string $method
     * @return bool
     */
    private function isHttpMethodFiltered($method)
    {
        $method = strtoupper($method);
        if ($this->getSecured && $method == "GET") {
            return true;
        } elseif ($this->postSecured && $method == "POST") {
            return true;
        } elseif ($this->putSecured && $method == "PUT") {
            return true;
        } elseif ($this->deleteSecured && $method == "DELETE") {
            return true;
        }
        return false;
    }

    /**
     * Private CsrfFilter constructor for singleton pattern.
     */
    private function __construct()
    {
        $this->request = RequestFactory::read();
        $configs = Configuration::getSecurityConfiguration();
        if (isset($configs['csrf_guard_req'])) {
            $methodsToFilter = $configs['csrf_guard_req'];
            $this->setPostSecured(in_array('POST', $methodsToFilter));
            $this->setPutSecured(in_array('PUT', $methodsToFilter));
            $this->setDeleteSecured(in_array('DELETE', $methodsToFilter));
            $this->setGetSecured(in_array('GET', $methodsToFilter));
        }
    }
}
