<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\App;
use GuzzleHttp\Client;
use \Session;
use GuzzleHttp\Exception\RequestException;
use InspireSoftware\MGSSO\Exceptions\Exception;

class MGSSOBroker
{
    protected $clientSecret;
    protected $clientId;
    protected $serverUrl;
    protected $mgSystemId;
    protected $http;
    protected $countryModel;

    /**
     * @method __construct
     */
    public function __construct(){
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if(!isset($_SESSION['inputErrors'])) $_SESSION['inputErrors'] = [];
        
        $this->clientId = env('SSO_CLIENT_ID');
        $this->clientSecret = env('SSO_CLIENT_SECRET');
        $this->serverUrl = env('API_URL_NETWORK', 'https://www.mgnetwork.xyz');

        if(substr($this->serverUrl, -1) == '/') $this->serverUrl = rtrim($this->serverUrl, '/');

        $this->mgSystemId = env('SSO_MG_SYSTEM_ID');
        $this->countryModel = env('COUNTRY_MODEL', 'mundogamer\Models\Country');

        $this->http = new Client([
            'base_uri' => $this->serverUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]
        ]);

        $this->adjustLanguage();
        $this->getSSOUser();
    }

    protected function adjustLanguage(){

        if(!isset($_SESSION['locale'])){
            $arr_ip = geoip()->getLocation($_SERVER['REMOTE_ADDR']);
            $country  = $this->countryModel::where('code2', $arr_ip['iso_code'])->first();
            if(!empty($country->language_id)){
                $activeLanguage = $country->language->name_abrev;
                $locale = $activeLanguage;
            }
        } else $locale = $_SESSION['locale'];

        Session::put('locale', $locale);
        $_SESSION['locale'] = $locale;
        App::setLocale($locale);
    }

    /**
     * @method getAccessToken
     */
    protected function getAccessToken(){
        return isset($_SESSION['MGSSO_ACCESS_TOKEN']) ? $_SESSION['MGSSO_ACCESS_TOKEN'] : null;
    }

    /**
     * @method setAuthResult
     */
    protected function setAuthResult($result){
        $_SESSION['MGSSO_ACCESS_TOKEN'] = $result['access_token'];
        $_SESSION['MGSSO_REFRESH_TOKEN'] = $result['refresh_token'];
        $_SESSION['MGSSO_TOKEN_EXPIRES_IN'] = $result['expires_in'];
    }

    /**
     * @method setSSOUser
     */
    protected function setSSOUser($user){
        $_SESSION['MGSSO_USER'] = $user;
        $this->verifyAuth($user);
    }

    /**
     * @method verifyAuth
     */
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

    /**
     * @method formParams
     */
    protected function formParams($params = []){

        if(isset($_SESSION['MGSSO_REFRESH_TOKEN'])) $params[] = $_SESSION['MGSSO_REFRESH_TOKEN'];

        return array_merge([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'mg_system_id' => $this->mgSystemId,
            'locale' => $this->getLocale(),
        ], $params);
    }

    /**
     * @method getSSOUser
     */
    public function getSSOUser(){
        if(isset($_SESSION['MGSSO_USER'])){
            $this->verifyAuth($_SESSION['MGSSO_USER']);
            return $_SESSION['MGSSO_USER'];
        } else if($this->getAccessToken()){
            $this->http = new Client([
                'base_uri' => $this->serverUrl,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                ]
            ]);
            try {
                $response = $this->http->get('api/sso/user', [
                    'form_params' => $this->formParams(),
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    ]
                ]);
                $user = json_decode((string) $response->getBody(), true);
                if($user) {
                    $this->setSSOUser($user);
                    return $this->getSSOUser();
                }
            } catch(RequestException $e){
                return dd($e->getResponse()->getBody(), $e->getResponse()->getContent());
            }
        }
        $this->logout(true);
        return null;
    }
    
    /**
     * @method login
     */
    public function login($email, $password){ 
        $currentUser = $this->getSSOUser();
        if(!$currentUser){
            try {
                $response = $this->http->post('oauth/token', [
                    'form_params' => [
                        'grant_type' => 'password',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'username' => $email,
                        'password' => $password,
                        'locale' => $this->getLocale(),
                        'mg_system_id' => $this->mgSystemId,
                    ],
                ]);
                $result = json_decode((string) $response->getBody(), true);
                $this->setAuthResult($result);
                return true;
            } catch(RequestException $e){
                $response = $e->getResponse();
                if($response){
                    $error = json_decode($response->getBody());
                    return $error;
                }
    
                return $e->getMessage();
                
            }
        }

        return false;
    }

    /**
     * @method logout
     */
    public function logout($ignoreRequest = false)
    {   
        if(!$ignoreRequest){
            try {
                $this->http->post('api/sso/logout', [
                    'form_params' => $this->formParams(),
                ]);
            } catch(RequestException $e){
            }
            Session::flush();
            if (session_status() != PHP_SESSION_NONE) session_destroy();
        } 
        
        Auth::logout();
        unset($_SESSION['MGSSO_USER']);
        unset($_SESSION['MGSSO_ACCESS_TOKEN']);
        unset($_SESSION['MGSSO_REFRESH_TOKEN']);
        unset($_SESSION['MGSSO_TOKEN_EXPIRES_IN']);
        
        Session::put('origin', MGSSOHelper::isMobile());
        Session::put('nav', MGSSOHelper::getBrowser());
    }

    /**
     * @method setLocale
     */
    public function setLocale($locale){
        Session::put('locale', $locale);
        $_SESSION['locale'] = $locale;
    }

    /**
     * @method getLocale
     */
    public function getLocale(){
        return $_SESSION['locale'];
    }

    /**
     * @method create
     */
    public function create($attributes){
        try {
            $response = $this->http->post('api/sso/create', [
                'form_params' => $this->formParams($attributes),
            ]);
            return json_decode($response->getBody(), true);
        } catch(RequestException $e){
            return dd($e->getResponse()->getBody());
        }
    }

    /**
     * @method sendToken
     */
    public function sendToken($email = null){

        try {
            $response = $this->http->post('api/sso/rescue-token', [
                'form_params' => [
                    'email' => $email,
                    'mg_system_id' => $this->mgSystemId,
                ],
            ]);
            return json_decode($response->getBody(), true);
        } catch(RequestException $e){
            $response = $e->getResponse();
            return dd($e->getMessage(), $response ? $response->getBody()->getContents() : null);
        }

    }

    /**
     * @method resetPassword
     */
    public function resetPassword($email){

        try {
            $response = $this->http->post('api/sso/reset-password', [
                'form_params' => [
                    'email' => $email,
                    'mg_system_id' => $this->mgSystemId,
                ],
            ]);
            return json_decode($response->getBody(), true);
        } catch(RequestException $e){
            $response = $e->getResponse();
            return dd($response->getBody()->getContents());
            return $response ? json_decode($response->getBody(), true) : $e->getMessage();
        }

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