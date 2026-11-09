<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Pinned to web, otherwise Spatie falls back to the passport guard on MCP requests and finds no permissions.
     */
    protected string $guard_name = 'web';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'must_change_password' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Every user is internal staff, so all of them can use the panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * The personal access tokens issued to automated agents acting as this user.
     *
     * @return HasMany<Token, $this>
     */
    public function agentTokens(): HasMany
    {
        $personalAccessClientIds = Passport::client()->newQuery()->get()
            ->filter(fn (Client $client): bool => $client->hasGrantType('personal_access'))
            ->modelKeys();

        return $this->hasMany(Token::class)->whereIn('client_id', $personalAccessClientIds);
    }

    /**
     * True when the current token is a personal access token, so no one is there to answer prompts.
     */
    public function isHeadless(): bool
    {
        $token = $this->token();

        return $token instanceof AccessToken
            && Passport::client()->newQuery()->find($token->oauth_client_id)?->hasGrantType('personal_access') === true;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
