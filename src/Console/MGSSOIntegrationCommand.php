<?php namespace InspireSoftware\MGSSO\Console;

use Illuminate\Console\Command;
use \GuzzleHttp\Client;

class MGSSOIntegrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mgsso:integration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make initial integration with SSOServer';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        
        $userModelClass = config('auth.providers.users.model');
        $userTableName = (new $userModelClass)->getTable();
        
        $users = $userModelClass::where('network_id', null)->get();
        $total = $users->count();

        if($total > 0){

            $bar = $this->output->createProgressBar($total);
            $bar->start();

            foreach($users as $user){

                $this->integrateUser($user);

                $bar->advance();

            }
            
            $bar->finish();

        }

        $this->info('all users are integrated!');

    }

    public function integrateUser($user){

        $postData = $user->toArray();
        $postData['password'] = $user->password;

        $http = new Client;
        $response = $http->request(
            'POST', 
            env('SSO_SERVER_URL'),
            [
                'form_params' => [
                    'command' => 'integrate-user',
                    'user' => $postData,
                ]
            ]
        );

        $result = json_decode($response->getBody()->getContents()); 

        $user->update([
            'network_id' => $result->data->id,
        ]);

        if($result->is_new){
            $this->info($user->email . ' created');
        }

    }

}