<?php

namespace KeycloakGuard;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use KeycloakGuard\Exceptions\ResourceAccessNotAllowedException;
use KeycloakGuard\Exceptions\TokenException;
use KeycloakGuard\Exceptions\UserNotFoundException;
use stdClass;

class KeycloakGuard implements Guard
{
    private $config;
    private $user;
    private $provider;
    private $decodedToken;
    private Request $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->config = config('keycloak');
        $this->user = null;
        $this->provider = $provider;
        $this->decodedToken = null;
        $this->request = $request;

        $this->authenticate();
    }

    /**
     * Decode token, validate and authenticate user
     */
    private function authenticate(): void
    {
        try {
            $this->decodedToken = Token::decode(
                $this->getTokenForRequest(),
                $this->config['realm_public_key'] ?? '',
                $this->config['realm_address'] ?? '',
                $this->config['leeway'] ?? 0,
            );
        } catch (\Exception $e) {
            abort(401, $e->getMessage());
        }

        if ($this->decodedToken) {
            $this->validate([$this->config['user_provider_credential'] => $this->getClientName()]);
        }
    }

    /**
     * Get the token for the current request.
     *
     * @return string
     */
    public function getTokenForRequest(): string
    {
        $inputKey = $this->config['input_key'] ?? '';

        return $this->request->bearerToken() ?? $this->request->input($inputKey) ?? Arr::get(getallheaders(), 'Authorization');
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the guard has a user instance.
     */
    public function hasUser(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Set the current user.
     *
     * @param Authenticatable $user
     * @return KeycloakGuard
     */
    public function setUser(Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null
     */
    public function user()
    {
        if (is_null($this->user)) {
            return null;
        }

        if ($this->config['append_decoded_token']) {
            $this->user->token = $this->decodedToken;
        }

        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id()
    {
        if ($user = $this->user()) {
            return $user?->id;
        }

        return null;
    }

    /**
     * Returns full decoded JWT token from authenticated user
     */
    public function token(): ?stdClass
    {
        return $this->decodedToken;
    }

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        $this->validateResources();

        if ($this->config['load_user_from_database']) {
            $methodOnProvider = $this->config['user_provider_custom_retrieve_method'] ?? null;

            if ($methodOnProvider) {
                $user = $this->provider->{$methodOnProvider}($this->decodedToken, $credentials);
            } else {
                $user = $this->provider->retrieveByCredentials($credentials);
            }

            if (!$user) {
                throw new UserNotFoundException("User not found. Credentials: ".json_encode($credentials));
            }
        } else {
            $class = $this->provider->getModel();
            $user = new $class();
        }

        $this->setUser($user);

        return true;
    }

    /**
     * Validate if authenticated user has a valid resource
     */
    private function validateResources(): void
    {
        if ($this->config['ignore_resources_validation']) {
            return;
        }

        $token_resource_access = Arr::get(
            (array) ($this->decodedToken->resource_access->{$this->getClientName()} ?? []),
            'roles',
            []
        );

        $allowed_resources = explode(',', $this->config['allowed_resources']);

        if (count(array_intersect($token_resource_access, $allowed_resources)) == 0) {
            throw new ResourceAccessNotAllowedException("The decoded JWT token has not a valid `resource_access` allowed by API. Allowed resources by API: ".$this->config['allowed_resources']);
        }
    }

    public function roles(bool $useGlobal = true): array
    {
        $global_roles = [];
        $client_roles = $this->decodedToken?->resource_access?->{$this->getClientName()}?->roles ?? [];

        if($useGlobal) {
            $global_roles = $this->decodedToken?->realm_access?->roles ?? [];
        }

//        $global_roles = $useGlobal === true ? $this->decodedToken?->realm_access?->roles ?? [] : [];

        return array_unique(
            array_merge(
                $global_roles,
                $client_roles
            )
        );
    }

    /**
     * Check if authenticated user has a especific role into resource
     * @param array|string $roles
     * @return bool
     */
    public function hasRole(array|string $roles): bool
    {
        return count(
                array_intersect(
                    $this->roles(),
                    is_string($roles) ? [$roles] : $roles
                )
            ) > 0;
    }

    public function scopes(): array
    {
        $scopes = $this->decodedToken->scope ?? null;

        if($scopes) {
            return explode(' ', $scopes);
        }

        return [];
    }

    public function hasScope(string|array $scope): bool
    {
        return count(array_intersect(
            $this->scopes(),
            is_string($scope) ? [$scope] : $scope
        )) > 0;
    }

    public function getRoles(): array
    {
        return $this->roles(false);
    }

    private function getClientName(): string|null
    {
        return $this->decodedToken->{$this->config['token_principal_attribute']};
    }
}
