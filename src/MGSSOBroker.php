<?php namespace InspireSoftware\MGSSO;

use Illuminate\Support\Facades\Auth;
use Jasny\SSO\Broker;
use Jasny\SSO\Exception;
use Jasny\SSO\NotAttachedException;
use \DB;
use App\Models\User;

class MGSSOBroker extends Broker
{
    public function __construct()
    {   
        parent::__construct(env('SSO_SERVER_URL'),env('SSO_CLIENT_ID'),env("SSO_CLIENT_SECRET"));
    }

    /**
     * Attach our session to the user's session on the SSO server.
     *
     * @param string|true $returnUrl  The URL the client should be returned to after attaching
     */
    public function attach($returnUrl = null)
    {
        if ($this->isAttached() || !isset($_SERVER['HTTP_HOST'])) return;

        $protocol = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $returnUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $params = ['return_url' => $returnUrl];
        $url = $this->getAttachUrl($params);
    }

    protected function request($method, $command, $data = null, $isFromProvider = false)
    {   
        if (!$isFromProvider && !$this->isAttached()) {
            throw new NotAttachedException('No token');
        }
        
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

        /// if(!$isFromProvider) dd($response, $httpCode);
        
        if ($httpCode == 403) {
            $this->clearToken();
            // throw new NotAttachedException(isset($data['error']) && $data['error'] ?: $response, $httpCode);
            return;
        }

        if ($httpCode >= 400) throw new Exception(isset($data['error']) && $data['error'] ?: $response, $httpCode);

        return $data;
    }

    /**
     * Get user information.
     *
     * @return object|null
     */
    public function getUserInfo($isFromProvider = false)
    {
        if (!isset($this->userinfo)) {
            $this->userinfo = $this->request('GET', 'userInfo', [], $isFromProvider);
        }

        return $this->userinfo;
    }

    public function loginUser($username, $password){
        try {
            $this->login($username, $password);
        }
        catch(NotAttachedException $e){
            return false;
        }
        catch(Exception $e){
            return false;
        }
        return true;
    }

    public function loginCurrentUser($returnUrl = '/', $redirect = true){
        $SSOUser = $this->getUserInfo($returnUrl === true);

        if($SSOUser){

            $userModelClass = config('auth.providers.users.model');
            $userTableName = (new $userModelClass)->getTable();
            
            $user = $userModelClass::where('network_id', $SSOUser['id'])->first();

            if(!$user){
                $data = $SSOUser;
                $data['network_id'] = $data['id'];
                unset($data['id']);

                $data['password'] = bcrypt(123456);
                $user = $userModelClass::query()->create($data);
            }
            
            if($redirect) return $this->onLoginSuccess($user->id, $returnUrl);

            Auth::loginUsingId($user->id, true);

        }
    }

    public static function createUser($data){

        $broker = new self();
        return $broker->request('POST', 'create-user', $data);

    }

    public static function flush(){

        $broker = new self();
        $broker->clearToken();
        $broker->userinfo = null;
        $broker->request('POST', 'logout', [], true);
    }

    public function onLoginSuccess($userId, $returnUrl = '/mgsso'){
        Auth::loginUsingId($userId, true);
        return redirect($returnUrl);
    }

    public function initialVerify(){
        $this->loginCurrentUser(true, false);
    }

}