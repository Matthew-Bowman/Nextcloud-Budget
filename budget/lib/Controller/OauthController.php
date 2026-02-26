<?php

declare(strict_types=1);

namespace OCA\Budget\Controller;

use OCA\Budget\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClientService;

class OauthController extends Controller
{

    protected $clientService;

    public function __construct(IRequest $request, IClientService $clientService)
    {
        parent::__construct(Application::APP_ID, $request);
        $this->clientService = $clientService;
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function monzoCallback(): DataResponse
    {
        // Var Declarations
        $code = $this->request->getParam('code');
        $state = $this->request->getParam('state');
        $missing = [];

        // Validation Checking
        if (!isset($code) || $code === null)
            $missing[] = 'code';

        if (!isset($state) || $state === null)
            $missing[] = 'status';

        if (count($missing) > 0)
            return new DataResponse(['message' => "Missing required parameter(s): " . join(', ', $missing)], Http::STATUS_BAD_REQUEST);


        // Core Logic
        $client = $this->clientService->newClient();

        try {


            $response = $client->post('https://api.monzo.com/oauth2/token', [
                "form_params" => [
                    'grant_type' => 'authorization_code',
                    'client_id' => 'oauth2client_0000B3UGkFD6uBYvAofHKE',
                    'client_secret' => 'mnzconf.vYxZQfPP1gPwBIQ+x9Gd1uSU2orZWE/ztv8foC6O/4NW/bnSO9722NgTDRakius9TUtdCW9Ym3q+ryXKEdVryQ==',
                    'redirect_uri' => 'https://nextcloud.home.lab/apps/budget/oauth/monzo/callback',
                    'code' => $code,
                ]
            ]);

            /**
             * Now I need to:
             * ==============
             * Store the tokens & expiry
             * Create centralised function for grabbing access token but checking if its expired before doing so and if it it:
             *  Use refresh token
             * 
             * Grab all accounts from the /accounts endpoint
             *  Use the Monzo provided account ids in the database
             *  Check if account with that monzo account id exists first though
             *      Don't store it if so
             *  Else store them
             *  Loop through the account IDs and grab all their balances then store it in the db
             *  Maybe make this into a function because the cron will do the same thing?
             *  */

            return new DataResponse([
                'status' => $response->getStatusCode(),
                'body' => $response->getBody(),
            ]);
        } catch (\Throwable $e) {
            return new DataResponse([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
