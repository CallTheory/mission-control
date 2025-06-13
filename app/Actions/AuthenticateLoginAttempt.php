<?php

namespace App\Actions;

use App\Models\DataSource;
use App\Models\Stats\Agents\Agent;
use App\Models\User;
use Exception;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Support\Facades\Hash;

class AuthenticateLoginAttempt
{
    public function __invoke($request): mixed
    {
        $user = User::where('email', $request->email)->first();

        if (is_null($user)) {
            return null;
        }

        // Normal login
        if (Hash::check($request->password, $user->password)) {
            return $user;
        }

        // ISWeb Agent Login
        $datasource = DataSource::first();
        if (is_null($datasource)) {
            return null;
        }

        try {
            $agentObject = new Agent(['agtId' => $user->agtId]);
            $agent = $agentObject->results[0];
        } catch (Exception $e) {
            return null;
        }

        if ($agent && isset($agent->Name)) {
            $guzzle = new Guzzle;

            try {
                $result = $guzzle->request('POST', "{$datasource->is_web_api_endpoint}/Login", [
                    'form_params' => [
                        'agent' => $agent->Name,
                        'password' => $request->password,
                    ],
                ]);
            } catch (Exception $e) {
                return null;
            }

            if ($result->getStatusCode() === 200) {
                $response = (string) $result->getBody();
                $json = json_decode($response, true);

                if ($json['AgentId'] === $user->agtId) {
                    try {
                        // make sure we logout so we don't take up a license
                        $result = $guzzle->request('GET', "{$datasource->is_web_api_endpoint}/Logout");
                    } catch (Exception $e) {
                    }

                    return $user;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
    }
}
