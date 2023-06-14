<?php

namespace KeycloakGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\CssSelector\Exception\InternalErrorException;

class KeycloakHelper
{
    public function getToken()
    {
        $token = Cache::get('keycloak_token');
        if(isset($token)) {
            return $token;
        }

        $token = $this->getKeycloakToken();

        Cache::put('keycloak_token', $token['access_token'], $token['expires_in']);

        return $token['access_token'];
    }

    private function getKeycloakToken()
    {
        $config = config('keycloak');
        $response = Http::asForm()->post($config['auth_url'],
            [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'scope' => $config['scope'],
                'grant_type' => $config['grant_type']
            ]
        );

        if ($response->ok()) {
            $body = $response->json();
            if (isset($body) && is_array($body) && isset($body['access_token'])) {
                return  ['access_token' => $body['access_token'], 'expires_in' => $body['expires_in']];
            }

            throw new InternalErrorException("KEYCLOAK - No access_token found.");
        }
        throw new InternalErrorException("KEYCLOAK - ".$response->status()." ".$response->body());
    }
}
