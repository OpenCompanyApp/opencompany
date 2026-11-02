<?php

namespace App\Domain\Connections;

use App\Models\Connection;
use App\Models\VaultSecret;
use Illuminate\Encryption\Encrypter;

/**
 * Encrypts and stores connection secrets.
 *
 * Owns the encryption key boundary: secrets use `VAULT_KEY` (base64:…) when
 * configured and otherwise the application key, so a deployment can rotate or
 * isolate the vault key independently. Reading is not public here: only the
 * CredentialBroker decrypts, at call time.
 */
class Vault
{
    public function put(Connection $connection, string $name, string $secret): VaultSecret
    {
        $existing = VaultSecret::query()->where('connection_id', $connection->id)->where('name', $name)->first();

        return VaultSecret::query()->updateOrCreate(
            ['connection_id' => $connection->id, 'name' => $name],
            [
                'workspace_id' => $connection->workspace_id,
                'ciphertext' => $this->encrypter()->encryptString($secret),
                'version' => $existing !== null ? $existing->version + 1 : 1,
            ],
        );
    }

    /** @internal Used by CredentialBroker only. */
    public function decrypt(VaultSecret $secret): string
    {
        return $this->encrypter()->decryptString($secret->ciphertext);
    }

    private function encrypter(): Encrypter
    {
        $key = (string) (config('vault.key') ?: config('app.key'));
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        return new Encrypter($key, (string) config('app.cipher', 'AES-256-CBC'));
    }
}
