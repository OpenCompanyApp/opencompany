<?php

namespace App\Domain\Ai\Codex\Stores;

use App\Domain\Ai\Codex\CodexTokenStore as CodexTokenModel;
use App\Domain\Ai\Codex\Contracts\CodexTokenStore;
use App\Domain\Ai\Codex\ValueObjects\CodexToken;

/**
 * Eloquent-backed implementation of Codex token storage.
 *
 * OpenCompany keeps a single active Codex token set. Replacing the package-owned
 * store here removes package coupling while preserving encrypted-at-rest
 * behavior and the existing codex_tokens table.
 */
final class EloquentCodexTokenStore implements CodexTokenStore
{
    public function current(): ?CodexToken
    {
        $model = CodexTokenModel::query()->latest()->first();

        if ($model === null) {
            return null;
        }

        return CodexToken::fromArray([
            'access_token' => $model->access_token,
            'refresh_token' => $model->refresh_token,
            'expires_at' => $model->expires_at,
            'account_id' => $model->account_id,
            'email' => $model->email,
            'token_data' => $model->token_data,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ]);
    }

    public function save(CodexToken $token): CodexToken
    {
        $data = [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_at' => $token->expiresAt,
            'account_id' => $token->accountId,
            'email' => $token->email,
            'token_data' => $token->tokenData,
        ];

        $existing = CodexTokenModel::query()->first();

        if ($existing !== null) {
            $existing->update($data);

            return $this->current() ?? $token;
        }

        CodexTokenModel::query()->create($data);

        return $this->current() ?? $token;
    }

    public function clear(): void
    {
        CodexTokenModel::query()->delete();
    }
}
