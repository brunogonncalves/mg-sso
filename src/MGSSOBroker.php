<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use GuzzleHttp\Client;
use \Session;
use GuzzleHttp\Exception\RequestException;
use InspireSoftware\MGSSO\Exceptions\Exception;
use InspireSoftware\MGSSO\Exceptions\NotAttachedException;
use \DB;
use App\Models\User;

class MGSSOBroker
{
    protected $clientSecret;
    protected $clientId;
    protected $serverUrl;
    protected $mgSystemId;
    protected $http;

    public function __construct(){
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->clientId = env('SSO_CLIENT_ID');
        $this->clientSecret = env('SSO_CLIENT_SECRET');
        $this->serverUrl = env('SSO_SERVER');
        $this->mgSystemId = env('SSO_MG_SYSTEM_ID');

        $this->http = new Client([
            'base_uri' => $this->serverUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]
        ]);
        $this->getSSOUser();
    }

    protected function getAccessToken(){
        return isset($_COOKIE['MGSSO_ACCESS_TOKEN']) ? $_COOKIE['MGSSO_ACCESS_TOKEN'] : null;
    }

    protected function setAuthResult($result){
        setcookie('MGSSO_ACCESS_TOKEN', $result['access_token'], time() + 3600, '/');
        setcookie('MGSSO_REFRESH_TOKEN', $result['refresh_token'], time() + 3600, '/');
        setcookie('MGSSO_TOKEN_EXPIRES_IN', $result['expires_in'], time() + 3600, '/');
    }

    protected function setSSOUser($user){
        $_SESSION['MGSSO_USER'] = $user;
        $this->verifyAuth($user);
    }

    protected function verifyAuth($SSOUser){

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

    }

    protected function formParams($params = []){
        return array_merge([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'access_token' => $this->getAccessToken(),
            'mg_system_id' => $this->mgSystemId,
        ], $params);
    }

    public function getSSOUser(){
        if(isset($_SESSION['MGSSO_USER'])){
            $this->verifyAuth($_SESSION['MGSSO_USER']);
            return $_SESSION['MGSSO_USER'];
        } else if($this->getAccessToken()){
            try {
                $response = $this->http->get('api/sso/user', [
                    'form_params' => $this->formParams(),
                ]);
                $user = json_decode((string) $response->getBody(), true);
                $this->setSSOUser($user);
                return $this->getSSOUser();
            } catch(RequestException $e){
            }
        }
        
        $this->logout();
        return null;
    }
    
    public function login($email, $password){ 
        try {
            $response = $this->http->post('oauth/token', [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'username' => $email,
                    'password' => $password,
                ],
            ]);
            $result = json_decode((string) $response->getBody(), true);
            $this->setAuthResult($result);
            return true;
        } catch(RequestException $e){
            $error = json_decode($e->getResponse()->getBody());
            return $error;
        }
    }

    /**
     * Logout at sso server.
     */
    public function logout()
    {   
        try {
            $result = $this->http->post('api/sso/logout', [
                'form_params' => $this->formParams(),
            ]);
            // return dd($result->getBody()->getContents());
        } catch(RequestException $e){
            // return dd('exception', $e->getResponse()->getBody()->getContents());
        }
        Auth::logout();
        unset($_SESSION['MGSSO_USER']);
        unset($_COOKIE['MGSSO_ACCESS_TOKEN']);
        unset($_COOKIE['MGSSO_REFRESH_TOKEN']);
        unset($_COOKIE['MGSSO_TOKEN_EXPIRES_IN']);
        Session::flush();
        session_destroy();

        Session::put('origin', MGSSOHelper::isMobile());
        Session::put('nav', MGSSOHelper::getBrowser());
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

    public function createUser($data){

        return $this->request('POST', 'create-user', $data);

    }

    /*public function sendLoginResponse(){
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
    } */

    public function resetPassword(){
        $userModelClass = config('auth.providers.users.model');
        
        return $this->request('POST', 'reset-password', ['email' => request('email')]);
    }

    public function sendToken($email = null){

        if(!$email) $email = request('email');
        return $this->request('POST', 'send-token', ['email' => $email]);

    }

    public function changePassword($oldPass, $newPass, $email){
        $result = $this->request('POST', 'change-password', [
            'oldPassword' => $oldPass,
            'newPassword' => $newPass,
            'email' => $email,
        ]);

        return $result;
    }

    public function checkEmail($email, $networkId){
        return $this->request('GET', 'check-email', ['email' => $email, 'id' => $networkId]);
    }

    public function setUserStatus($status){
        $user = auth()->user();
        return $this->request('POST', 'set-user-status', [
            'email' => $user->email, 
            'id' => $user->network_id, 
            'status' => $status,
        ]);
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