<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Session;
use InspireSoftware\MGSSO\Exceptions\Exception;
use InspireSoftware\MGSSO\Exceptions\NotAttachedException;
use \DB;
use App\Models\User;

class MGSSOBroker
{
    /**
     * Url of SSO server
     * @var string
     */
    protected $url;

    /**
     * My identifier, given by SSO provider.
     * @var string
     */
    public $broker;

    /**
     * My secret word, given by SSO provider.
     * @var string
     */
    protected $secret;

    /**
     * Session token of the client
     * @var string
     */
    public $token;

    /**
     * Cookie lifetime
     * @var int
     */
    protected $cookie_lifetime;

    /**
     * Class constructor
     *
     * @param string $url    Url of SSO server
     * @param string $broker My identifier, given by SSO provider.
     * @param string $secret My secret word, given by SSO provider.
     */
    public function __construct()
    {
        $url = config('mgsso.credential.server');
        $broker = config('mgsso.credential.system_id');
        $secret = config('mgsso.credential.system_secret');
        $cookie_lifetime = 3600;

        if (!$url) throw new \InvalidArgumentException("SSO server URL not specified");
        if (!$broker) throw new \InvalidArgumentException("SSO broker id not specified");

        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;
        $this->cookie_lifetime = $cookie_lifetime;

        if (isset($_COOKIE[$this->getCookieName()])) $this->token = $_COOKIE[$this->getCookieName()];
    }

    /**
     * Get the cookie name.
     *
     * Note: Using the broker name in the cookie name.
     * This resolves issues when multiple brokers are on the same domain.
     *
     * @return string
     */
    protected function getCookieName()
    {
        return 'sso_token_' . preg_replace('/[_\W]+/', '_', strtolower($this->broker));
    }

    /**
     * Generate session id from session key
     *
     * @return string
     */
    protected function getSessionId()
    {
        if (!isset($this->token)) return null;

        $checksum = hash('sha256', 'session' . $this->token . $this->secret);
        return "SSO-{$this->broker}-{$this->token}-$checksum";
    }

    /**
     * Generate session token
     */
    public function generateToken()
    {
        if (isset($this->token)) return;

        $this->token = base_convert(md5(uniqid(rand(), true)), 16, 36);
        setcookie($this->getCookieName(), $this->token, time() + $this->cookie_lifetime, '/');
    }

    /**
     * Clears session token
     */
    public function clearToken()
    {
        setcookie($this->getCookieName(), null, 1, '/');
        $this->token = null;
    }

    /**
     * Check if we have an SSO token.
     *
     * @return boolean
     */
    public function isAttached()
    {
        return isset($this->token);
    }

    /**
     * Get URL to attach session at SSO server.
     *
     * @param array $params
     * @return string
     */
    public function getAttachUrl($params = [])
    {
        $this->generateToken();

        $data = [
            'command' => 'attach',
            'broker' => $this->broker,
            'token' => $this->token,
            'checksum' => hash('sha256', 'attach' . $this->token . $this->secret)
        ] + $_GET;

        return $this->url . "?" . http_build_query($data + $params);
    }

    /**
     * Attach our session to the user's session on the SSO server.
     *
     * @param string|true $returnUrl  The URL the client should be returned to after attaching
     */
    public function attach($returnUrl = null)
    {
        Session::put('origin', MGSSOHelper::isMobile());
        Session::put('nav', MGSSOHelper::getBrowser());
        
        if ($this->isAttached() || !isset($_SERVER['HTTP_HOST'])) return;

        $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $returnUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $params = ['return_url' => $returnUrl];
        $url = $this->getAttachUrl($params);
    }

    /**
     * Get the request url for a command
     *
     * @param string $command
     * @param array  $params   Query parameters
     * @return string
     */
    protected function getRequestUrl($command, $params = [])
    {
        $params['command'] = $command;
        return $this->url . '?' . http_build_query($params);
    }

    protected function request($method, $command, $data = null, $ignoreExceptions = false)
    {   
        if(!$this->isAttached()) $this->attach();
        
        if(is_array($data)){
            $data['token'] = $this->token;
            $data['broker'] = $this->broker;
            $data['checksum'] = hash('sha256', 'session' . $this->token . $this->secret);
        }

        $url = $this->getRequestUrl($command, !$data || $method === 'POST' ? [] : $data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Authorization: Bearer '. $this->getSessionID()]);

        if (empty($data)) $data = [];
        $post = is_string($data) ? $data : http_build_query($data);

        if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $response = curl_exec($ch); 
        if (curl_errno($ch) != 0) {
            $message = 'Server request failed: ' . curl_error($ch);
            throw new Exception($message);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        list($contentType) = explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        
        $data = json_decode($response, true);

        if (!$ignoreExceptions && $httpCode >= 400 && isset($data['error'])) throw new Exception($data['error'] ?: $response, $httpCode);

        return $data;
    }

    /**
     * Log the client in at the SSO server.
     *
     * Only brokers marked trused can collect and send the user's credentials. Other brokers should omit $username and
     * $password.
     *
     * @param string $username
     * @param string $password
     * @return array  user info
     * @throws Exception if login fails eg due to incorrect credentials
     */
    public function login($username = null, $password = null)
    {
        if (!isset($username)) $username = request('username');
        if (!isset($password)) $password = request('password');

        $currentUser = $this->getUserInfo();

        if(!$currentUser){
            $SSOUser = $this->request('POST', 'login', compact('username', 'password'), true);

            if($SSOUser && isset($SSOUser['id'])) return $this->onSuccessLogin($SSOUser);

            return null;

        }

        return $currentUser;
    }

    public function loginUsingId($userId){
        $userModelClass = config('auth.providers.users.model');
        $user = $userModelClass::findOrFail($userId);

        $data = $user->toArray();
        $data['password'] = bcrypt('mGgamer+159');
        $SSOUser = $this->request('POST', 'force-login', $data);

        if($SSOUser && isset($SSOUser['id'])) {
            $user->update(['network_id' => $SSOUser['id']]);
            return $this->onSuccessLogin($SSOUser);
        }

    }

    /**
     * Logout at sso server.
     */
    public function logout()
    {
        $this->request('POST', 'logout', [], true);
        Auth::logout();
        $this->clearToken();
        Session::forget('user');
        
        $origin = Session::get('origin');
        $browser = Session::get('nav');

        Session::flush();

        Session::put('origin', $origin);
        Session::put('nav', $browser);
    }

    /**
     * Get user information.
     *
     * @return object|null
     */
    public function getUserInfo()
    {
        if (!Session::exists('user')) {
            $SSOUser = $this->request('GET', 'userInfo', [], true);
            if($SSOUser && isset($SSOUser['id'])) Session::put('user', $SSOUser);
            else Session::forget('user');
        }

        $user = Session::get('user');

        return $user && isset($user['id']) ? $user : null;
    }

    public function createUser($data){

        return $this->request('POST', 'create-user', $data);

    }

    public function sendLoginResponse(){
        $user = Auth::user();

        if($user){
            if(!$user->verified){
                $SSOUser = $this->getUserInfo();
                if(!$SSOUser || !isset($SSOUser['verified']) || !$SSOUser['verified']) {
                    
                    // send email for verify
                    $this->request('POST', 'send-email-verify', ['userId' => $SSOUser['id']]);
                    
                    $phrase =  Lang::get('loginReg.EmailMessagePhrase1');
                    $this->logout();
                    return back()->with('warning', $phrase);
                }
            }
    
            if(empty($user->terms_use) || empty($user->policy)) return redirect('terms-user');
            if(empty($user->nickname) || empty($user->date_birth)) return redirect('step');
        }

        return redirect('/');
    }

    public function resetPassword(){
        $userModelClass = config('auth.providers.users.model');
        
        return $this->request('POST', 'reset-password', ['email' => request('email')]);
    }

    public function onSuccessLogin($SSOUser){

        if (!$SSOUser || !isset($SSOUser['id'])) return null;

        Session::put('user', $SSOUser);
        Session::put('origin', MGSSOHelper::isMobile());
        Session::put('nav', MGSSOHelper::getBrowser());

        $userModelClass = config('auth.providers.users.model');
        $userTableName = (new $userModelClass)->getTable();
        
        $user = $userModelClass::where('network_id', $SSOUser['id'])->first();

        if(!$user){
            $data = $SSOUser;
            $data['network_id'] = $data['id'];
            unset($data['id']);
            $data['password'] = bcrypt('notnecessary');

            $user = $userModelClass::query()->create($data);
        }

        Auth::loginUsingId($user->id, true);
        if($SSOUser['verified'] && !$user->verified) $user->update(['verified' => 1]);

        return $user;
    }

    public function sendToken($email = null){

        if(!$email) $email = request('email');
        return $this->request('POST', 'send-token', ['email' => $email]);

    }

    /**
     * Magic method to do arbitrary request
     *
     * @param string $fn
     * @param array  $args
     * @return mixed
     */
    public function __call($fn, $args)
    {
        $sentence = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $fn));
        $parts = explode(' ', $sentence);

        $method = count($parts) > 1 && in_array(strtoupper($parts[0]), ['GET', 'DELETE'])
            ? strtoupper(array_shift($parts))
            : 'POST';
        $command = join('-', $parts);

        return $this->request($method, $command, $args);
    }

}